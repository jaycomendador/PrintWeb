# ESP32 Status Indicator & Buzzer Setup Guide

This guide explains how to connect your **ESP32 microcontroller** with **3x LEDs and a Buzzer** to the PrintWeb Smart Printing System.

---

## Hardware Components Required
1. **ESP32 Development Board** (NodeMCU ESP32, ESP32-WROOM-32, etc.)
2. **1x Green LED** (Printer Available / Ready)
3. **1x Yellow / Orange LED** (Printing in Progress)
4. **1x Red LED** (Error / Offline / Out of Paper)
5. **3x 220Ω – 330Ω Resistors** (for LEDs)
6. **1x 5V/3.3V Active/Passive Buzzer** (Audio status notifications)
7. Breadboard & Jumper wires

---

## Wiring Diagram

| Component | ESP32 GPIO Pin | Connection Notes |
| :--- | :--- | :--- |
| **Green LED Anode (+)** | **GPIO 18** | Connect via 220Ω resistor to GPIO 18, Cathode (-) to GND |
| **Yellow LED Anode (+)**| **GPIO 19** | Connect via 220Ω resistor to GPIO 19, Cathode (-) to GND |
| **Red LED Anode (+)**   | **GPIO 21** | Connect via 220Ω resistor to GPIO 21, Cathode (-) to GND |
| **Buzzer Positive (+)** | **GPIO 22** | Connect directly or via NPN transistor to GPIO 22 |
| **Buzzer Negative (-)** | **GND** | Connect to ESP32 Common Ground |

---

## LED & Buzzer Signal Reference

| Printer State | Active LED | Buzzer Acoustic Signal | Meaning |
| :--- | :--- | :--- | :--- |
| **Available / Idle** | 🟢 **Green** | Silent | Printer is online and ready for new print jobs |
| **Payment Successful** | 🟢 **Green** | 2 short beeps | Payment has been accepted and a job entered the queue |
| **Printing Job** | 🟡 **Yellow** | 1 short beep on start | Print job is currently being processed |
| **Job Completed** | 🟢 **Green** | 3 short beeps | Print job has finished printing |
| **Error / Jam / Paper Out**| 🔴 **Red** | Repeating warning beeps | Attention required on the printer |
| **Offline / Disconnected** | 🔴 **Red** | Silent / 1 Long Alarm | Microcontroller or network unreachable |

---

## Arduino IDE Flashing Instructions

1. Open `esp32_firmware.ino` in the Arduino IDE.
2. In **Tools > Manage Libraries...**, install:
   - **ArduinoJson** (by Benoit Blanchon, Version 6 or 7)
3. Under **Configuration** in `esp32_firmware.ino`, configure:
   ```cpp
   const char* WIFI_SSID     = "Your_WiFi_Name";
   const char* WIFI_PASSWORD = "Your_WiFi_Password";
   const char* SERVER_URL    = "http://192.168.1.XXX/PrintWeb/api/esp32.php";
   const char* DEVICE_KEY    = "COPY_API_KEY_FROM_ADMIN_PANEL";
   ```
4. Select board **ESP32 Dev Module**, choose your COM port, and click **Upload**.
5. Open Serial Monitor at **115200 baud** to see real-time heartbeat and server responses.

Register the controller in **Admin → Printer Management**, then use **Copy key** in the ESP32 device list to copy its generated API key into `DEVICE_KEY`. The web app updates controller connectivity from its heartbeat. Printer availability and print progress are managed by the admin queue unless you add compatible printer sensors and submit status reports to the ESP32 API.

## Scan a controller from PrintWeb

The admin's **Printer Management → Scan USB** feature uses the browser's Web Serial API to request access to a USB serial port and identify the board. Use a current version of Chrome or Edge and open the site on `localhost` or over HTTPS. Browsers require the administrator to select a port; websites cannot silently enumerate or open every attached USB device.

First upload this updated firmware once with its default configuration; USB discovery works even if Wi-Fi is not configured. Connect the ESP32 with a USB data cable, close Arduino IDE or Thonny serial monitors, choose its port in the browser permission dialog, and register the detected device. Then use **Copy key** in the device list to copy the generated API key into `DEVICE_KEY`, configure Wi-Fi and the reachable `SERVER_URL`, and upload the firmware again. USB detection confirms that the board responds over serial. The dashboard's **Connected** state still means that the configured firmware is reaching the server over Wi-Fi.
