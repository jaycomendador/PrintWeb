/*
  Smart Printing Payment System - ESP32 Firmware
  Hardware Indicator: 3x LEDs (Green, Yellow, Red) & Active/Passive Buzzer
  
  Connections:
  - Green LED   -> GPIO 18 (with 220-330 ohm resistor to GND)
  - Yellow LED  -> GPIO 19 (with 220-330 ohm resistor to GND)
  - Red LED     -> GPIO 21 (with 220-330 ohm resistor to GND)
  - Buzzer (+)  -> GPIO 22 (Buzzer (-) to GND)
  
  Behavior:
  - Green LED ON  : Printer Available & Ready
  - Yellow LED ON : Printing in progress
  - Red LED ON    : Printer Offline / Error / Paper Low
  - Buzzer Beeps  : 1 on new print job, 2 on payment, 3 on completion, repeating pattern on error
*/

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// ===================== CONFIGURATION =====================
const char* WIFI_SSID     = "YOUR_WIFI_SSID";
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";

// Server configuration (adjust IP of your XAMPP host computer)
const char* SERVER_URL    = "http://192.168.1.100/PrintWeb/api/esp32.php";
const char* DEVICE_KEY    = "COPY_API_KEY_FROM_ADMIN_PANEL";
const char* FIRMWARE_VERSION = "1.1.0";

// Pin Definitions
#define PIN_LED_GREEN   18
#define PIN_LED_YELLOW  19
#define PIN_LED_RED     21
#define PIN_BUZZER      22

// Timing
unsigned long lastPollTime = 0;
unsigned long pollInterval = 3000; // 3 seconds default
char serialCommandBuffer[64];
size_t serialCommandLength = 0;

// ===================== SETUP =====================
void setup() {
  Serial.begin(115200);
  delay(500);
  Serial.println("\n--- Smart Printing System ESP32 Indicator Starting ---");

  // Configure Pins
  pinMode(PIN_LED_GREEN, OUTPUT);
  pinMode(PIN_LED_YELLOW, OUTPUT);
  pinMode(PIN_LED_RED, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);

  // Self-test LEDs and Buzzer on boot
  testHardware();

  // Connect to WiFi
  connectToWiFi();
}

// ===================== MAIN LOOP =====================
void loop() {
  processSerialCommands();

  // Maintain WiFi
  if (WiFi.status() != WL_CONNECTED) {
    setLeds(false, false, true); // Red LED when disconnected
    connectToWiFi();
  }

  // Poll server on schedule
  if (millis() - lastPollTime >= pollInterval) {
    lastPollTime = millis();
    pollServerStatus();
  }
}

// USB discovery handshake used by the PrintWeb admin's Web Serial scanner.
void processSerialCommands() {
  while (Serial.available() > 0) {
    const char incoming = (char)Serial.read();
    if (incoming == '\r') continue;

    if (incoming == '\n') {
      serialCommandBuffer[serialCommandLength] = '\0';
      if (strcmp(serialCommandBuffer, "PRINTWEB_SCAN") == 0) {
        sendDiscoveryResponse();
      }
      serialCommandLength = 0;
    } else if (serialCommandLength < sizeof(serialCommandBuffer) - 1) {
      serialCommandBuffer[serialCommandLength++] = incoming;
    } else {
      serialCommandLength = 0;
    }
  }
}

void sendDiscoveryResponse() {
  char deviceId[32];
  char deviceName[40];
  const uint64_t chipId = ESP.getEfuseMac() & 0xFFFFFFFFFFFFULL;
  snprintf(deviceId, sizeof(deviceId), "ESP32-%012llX", (unsigned long long)chipId);
  snprintf(deviceName, sizeof(deviceName), "ESP32 Controller %04X", (unsigned int)(chipId & 0xFFFF));

  StaticJsonDocument<192> response;
  response["protocol"] = "printweb-esp32";
  response["device_id"] = deviceId;
  response["device_name"] = deviceName;
  response["firmware_version"] = FIRMWARE_VERSION;
  serializeJson(response, Serial);
  Serial.println();
}

// ===================== HARDWARE TEST =====================
void testHardware() {
  Serial.println("Performing Hardware Self-Test...");
  
  digitalWrite(PIN_LED_GREEN, HIGH);
  delay(200);
  digitalWrite(PIN_LED_GREEN, LOW);
  
  digitalWrite(PIN_LED_YELLOW, HIGH);
  delay(200);
  digitalWrite(PIN_LED_YELLOW, LOW);
  
  digitalWrite(PIN_LED_RED, HIGH);
  delay(200);
  digitalWrite(PIN_LED_RED, LOW);

  // Quick boot chirp
  tone(PIN_BUZZER, 2000, 100);
  delay(150);
}

// ===================== WIFI CONNECTION =====================
void connectToWiFi() {
  Serial.print("Connecting to WiFi: ");
  Serial.println(WIFI_SSID);
  
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    digitalWrite(PIN_LED_YELLOW, !digitalRead(PIN_LED_YELLOW));
    delay(500);
    processSerialCommands();
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWiFi Connected!");
    Serial.print("ESP32 IP Address: ");
    Serial.println(WiFi.localIP());
    setLeds(true, false, false); // Green ON
  } else {
    Serial.println("\nWiFi Connection Failed! Retrying in loop...");
    setLeds(false, false, true); // Red ON
  }
}

// ===================== LED CONTROLLER =====================
void setLeds(bool green, bool yellow, bool red) {
  digitalWrite(PIN_LED_GREEN, green ? HIGH : LOW);
  digitalWrite(PIN_LED_YELLOW, yellow ? HIGH : LOW);
  digitalWrite(PIN_LED_RED, red ? HIGH : LOW);
}

// ===================== BUZZER COMMANDS =====================
void playBuzzerCommand(String command) {
  if (command == "beep_new_job") {
    // Single short notification when a queued job starts.
    tone(PIN_BUZZER, 1800, 150);
  } 
  else if (command == "beep_payment") {
    for (int i = 0; i < 2; i++) {
      tone(PIN_BUZZER, 1800, 120);
      delay(180);
    }
  }
  else if (command == "beep_complete") {
    for (int i = 0; i < 3; i++) {
      tone(PIN_BUZZER, 2200, 120);
      delay(180);
    }
  } 
  else if (command == "alert_error") {
    // Repeat the warning pattern so a printer fault is noticeable.
    for (int i = 0; i < 8; i++) {
      tone(PIN_BUZZER, 800, 100);
      delay(250);
    }
  }
}

// ===================== POLL SERVER =====================
void pollServerStatus() {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  String url = String(SERVER_URL) + "?device_key=" + String(DEVICE_KEY) + "&ip_address=" + WiFi.localIP().toString();
  
  http.begin(url);
  http.setTimeout(3000);
  int httpCode = http.GET();

  if (httpCode == HTTP_CODE_OK) {
    String payload = http.getString();
    
    // Parse JSON
    StaticJsonDocument<1024> doc;
    DeserializationError error = deserializeJson(doc, payload);

    if (!error && doc["success"].as<bool>()) {
      const char* ledStatus   = doc["led_status"] | "green";
      const char* buzzerCmd   = doc["buzzer_command"] | "";
      int serverPollInterval  = doc["poll_interval_sec"] | 3;

      pollInterval = serverPollInterval * 1000;

      // Update Physical LEDs
      if (strcmp(ledStatus, "yellow") == 0) {
        setLeds(false, true, false); // Printing
      } else if (strcmp(ledStatus, "red") == 0) {
        setLeds(false, false, true); // Error / Offline
      } else {
        setLeds(true, false, false); // Available / Ready
      }

      // Trigger Buzzer if command present
      if (strlen(buzzerCmd) > 0) {
        Serial.print("Buzzer command received: ");
        Serial.println(buzzerCmd);
        playBuzzerCommand(String(buzzerCmd));
      }
    } else {
      Serial.println("JSON parse error from server.");
    }
  } else {
    Serial.printf("HTTP GET failed, error: %s (code %d)\n", http.errorToString(httpCode).c_str(), httpCode);
    setLeds(false, false, true); // Red on server failure
  }
  
  http.end();
}
