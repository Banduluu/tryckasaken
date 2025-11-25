#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <SH1106Wire.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include "time.h"

// ================== DOCUMENTATION ==================
/*
 * RFID Attendance System for ESP32
 * 
 * PIN CONFIGURATION:
 * ==================
 * OLED Display (I2C):
 *   - SDA: GPIO 21
 *   - SCL: GPIO 22
 *   - Address: 0x3C
 * 
 * RC522 (SPI):
 *   - CS/SS: GPIO 5
 *   - RST: GPIO 4
 *   - MOSI: GPIO 23
 *   - MISO: GPIO 19
 *   - SCK: GPIO 18
 * 
 * Outputs:
 *   - Buzzer: GPIO 15
 *   - Green LED: GPIO 25
 *   - Red LED: GPIO 26
 * 
 * SERVER CONFIGURATION:
 * =====================
 * Update serverURL to match your local network IP
 * Example: "http://192.168.1.105/tryckasaken/pages/driver/rfid-attendance-handler.php"
 */

// ================== OLED SETUP (ESP32 I2C) ==================
SH1106Wire display(0x3C, 21, 22);

// ================== RC522 SETUP (ESP32 SPI) ==================
#define SS_PIN   5
#define RST_PIN  4
MFRC522 rfid(SS_PIN, RST_PIN);

// ================== BUZZER & LED ==================
#define BUZZER 15
#define GREEN_LED 25
#define RED_LED   26

// ================== NTP TIME CONFIG ==================
const char* ntpServer = "pool.ntp.org";
const long  gmtOffset_sec = 8 * 3600;  // UTC+8 (Philippines)
const int   daylightOffset_sec = 0;

// ================== SERVER API ENDPOINT ==================
// IMPORTANT: CHANGE THIS TO YOUR SERVER URL
const char* serverURL = "http://192.168.1.8/tryckasaken/pages/driver/rfid-attendance-handler.php";
// Your current server IP address: 192.168.1.8

const char* learningStatusURL = "http://192.168.1.8/tryckasaken/pages/driver/rfid-learning-handler.php?action=status";

// ================== WiFi NETWORKS ==================
struct WiFiNetwork {
  const char* ssid;
  const char* password;
};

WiFiNetwork networks[] = {
  {"Wu-Tang LAN", "Passkeys@1234"},
};

int totalNetworks = sizeof(networks) / sizeof(networks[0]);

// ================== CENTERED TEXT HELPER ==================
void drawCenteredText(String text, int y, int size) {
  if (size == 1) display.setFont(ArialMT_Plain_10);
  if (size == 2) display.setFont(ArialMT_Plain_16);
  if (size == 3) display.setFont(ArialMT_Plain_24);

  int w = display.getStringWidth(text);
  int x = (128 - w) / 2;
  display.drawString(x, y, text);
}

// ================== WiFi CONNECTION ==================
void connectToWiFi() {
  display.clear();
  drawCenteredText("Connecting WiFi...", 0, 1);
  display.display();

  for (int i = 0; i < totalNetworks; i++) {
    Serial.print("Trying: ");
    Serial.println(networks[i].ssid);

    WiFi.begin(networks[i].ssid, networks[i].password);

    int retries = 0;
    while (WiFi.status() != WL_CONNECTED && retries < 10) {
      delay(500);
      Serial.print(".");
      retries++;
    }

    if (WiFi.status() == WL_CONNECTED) {
      Serial.println("\nConnected!");
      Serial.print("IP Address: ");
      Serial.println(WiFi.localIP());
      
      display.clear();
      drawCenteredText("Connected To:", 0, 1);
      drawCenteredText(networks[i].ssid, 16, 2);
      display.display();
      delay(1500);

      return;
    }
  }

  Serial.println("\nNo networks available.");
}

// ================== AUTO RECONNECT ==================
void checkReconnect() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi lost! Reconnecting...");

    display.clear();
    drawCenteredText("WiFi Lost!", 16, 2);
    drawCenteredText("Reconnecting...", 40, 1);
    display.display();

    int maxRetries = 10;
    for (int attempt = 0; attempt < maxRetries && WiFi.status() != WL_CONNECTED; attempt++) {
      digitalWrite(RED_LED, HIGH);
      delay(300);
      digitalWrite(RED_LED, LOW);
      delay(300);

      WiFi.begin(networks[attempt % totalNetworks].ssid, networks[attempt % totalNetworks].password);
    }

    digitalWrite(RED_LED, LOW);

    if (WiFi.status() == WL_CONNECTED) {
      display.clear();
      drawCenteredText("Connected To:", 0, 1);
      drawCenteredText(WiFi.SSID(), 16, 2);
      display.display();
      delay(1500);
    }
  }
}

// ================== LED + BUZZER FEEDBACK ==================
void syncedBlinkBeep(int ledPin, int times, int duration) {
  for (int i = 0; i < times; i++) {
    digitalWrite(ledPin, HIGH);
    digitalWrite(BUZZER, LOW);
    delay(duration);

    digitalWrite(ledPin, LOW);
    digitalWrite(BUZZER, HIGH);
    delay(duration);
  }
}

// ================== SEND RFID DATA TO SERVER ==================
bool sendToServer(String uid, String action, String &serverMessage, String &actualAction, String &driverName) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi not connected!");
    serverMessage = "No WiFi";
    return false;
  }

  HTTPClient http;
  http.begin(serverURL);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  // Create JSON payload
  StaticJsonDocument<200> doc;
  doc["uid"] = uid;
  doc["action"] = action;

  String jsonPayload;
  serializeJson(doc, jsonPayload);

  Serial.println("Sending to server: " + jsonPayload);
  Serial.println("URL: " + String(serverURL));

  int httpResponseCode = http.POST(jsonPayload);

  if (httpResponseCode > 0) {
    String response = http.getString();
    Serial.println("Response code: " + String(httpResponseCode));
    Serial.println("Response: " + response);

    http.end();

    // Parse response
    StaticJsonDocument<512> responseDoc;
    DeserializationError error = deserializeJson(responseDoc, response);

    if (!error) {
      serverMessage = responseDoc["message"].as<String>();
      actualAction = responseDoc["action"].as<String>();
      
      // Get driver name from response
      if (responseDoc.containsKey("driver")) {
        driverName = responseDoc["driver"]["name"].as<String>();
      }
      
      return responseDoc["success"];
    } else {
      serverMessage = "Parse Error";
      return false;
    }
  } else {
    Serial.println("Error sending data: " + String(httpResponseCode));
    serverMessage = "Connection Failed";
  }

  http.end();
  return false;
}

// ================== CHECK LEARNING MODE STATUS ==================
bool isLearningModeActive() {
  if (WiFi.status() != WL_CONNECTED) {
    return false;
  }

  HTTPClient http;
  http.begin(learningStatusURL);
  http.setTimeout(3000);

  int httpResponseCode = http.GET();

  if (httpResponseCode > 0) {
    String response = http.getString();
    http.end();

    StaticJsonDocument<256> responseDoc;
    DeserializationError error = deserializeJson(responseDoc, response);

    if (!error && responseDoc["success"]) {
      return responseDoc["enabled"].as<bool>();
    }
  }

  http.end();
  return false;
}

// ================== SETUP ==================
void setup() {
  Serial.begin(115200);
  Serial.println("\n\n=== RFID Attendance System Starting ===");

  // Initialize SPI
  SPI.begin(18, 19, 23);
  rfid.PCD_Init();

  // Initialize outputs
  pinMode(BUZZER, OUTPUT);
  digitalWrite(BUZZER, HIGH);

  pinMode(GREEN_LED, OUTPUT);
  pinMode(RED_LED, OUTPUT);

  // Initialize display
  display.init();
  display.flipScreenVertically();
  display.clear();
  display.setContrast(255);

  connectToWiFi();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("Testing server connection...");
    display.clear();
    drawCenteredText("Testing Server...", 20, 1);
    display.display();
    delay(1000);
  }

  // Sync time from NTP server
  configTime(gmtOffset_sec, daylightOffset_sec, ntpServer);

  delay(2000); // Wait for time sync

  Serial.println("System Ready!");
}

// ================== MAIN LOOP ==================
void loop() {
  checkReconnect();

  // Check if RFID card is detected
  if (rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial()) {

    // Read card UID
    String uid = "";
    for (byte i = 0; i < rfid.uid.size; i++) {
      if (rfid.uid.uidByte[i] < 16) uid += "0";
      uid += String(rfid.uid.uidByte[i], HEX);
    }
    uid.toUpperCase();

    Serial.println("\n=== Card Detected ===");
    Serial.println("UID: " + uid);

    // Show processing
    display.clear();
    drawCenteredText("Processing...", 20, 2);
    display.display();

    // Always query server to check card and determine action
    // The server will handle tap count logic and return appropriate response
    String serverMessage = "";
    String actualAction = "";
    String driverName = "";
    
    // Send request, server will determine correct action based on driver's current status
    bool serverSuccess = sendToServer(uid, "online", serverMessage, actualAction, driverName);

    if (serverSuccess) {
      // Card recognized by server
      // Blink LED based on action: 1 blink for online, 2 blinks for offline
      int blinkCount = (actualAction == "online") ? 1 : 2;
      syncedBlinkBeep(GREEN_LED, blinkCount, 150);

      struct tm timeinfo;
      getLocalTime(&timeinfo);

      char buffer[30];
      strftime(buffer, sizeof(buffer), "%b %d, %Y %I:%M:%S %p", &timeinfo);

      String status = (actualAction == "online") ? "Going Online" : "Going Offline";

      display.clear();
      display.setFont(ArialMT_Plain_10);
      display.drawString(0, 0, driverName);
      display.drawString(0, 16, status);
      display.drawString(0, 32, buffer);
      display.drawString(0, 48, "UID: " + uid);
      display.display();

      Serial.println("SUCCESS: " + driverName + " - " + status);

    } else {
      // Unknown card or server error
      display.clear();
      display.setFont(ArialMT_Plain_10);
      
      if (serverMessage == "Unknown card") {
        // Check if learning mode is active
        bool learningMode = isLearningModeActive();
        
        if (learningMode) {
          // Learning mode is ACTIVE - show detection message
          drawCenteredText("LEARNING MODE", 0, 1);
          display.drawString(0, 16, "New Card Detected!");
          display.drawString(0, 32, "UID: " + uid);
          display.drawString(0, 48, "Register in Admin");
          
          // Flash both LEDs alternately for learning mode
          for (int i = 0; i < 3; i++) {
            digitalWrite(GREEN_LED, HIGH);
            digitalWrite(BUZZER, LOW);
            delay(100);
            digitalWrite(GREEN_LED, LOW);
            digitalWrite(BUZZER, HIGH);
            delay(100);
            digitalWrite(RED_LED, HIGH);
            digitalWrite(BUZZER, LOW);
            delay(100);
            digitalWrite(RED_LED, LOW);
            digitalWrite(BUZZER, HIGH);
            delay(100);
          }
          
          Serial.println("LEARNING MODE: New card detected - " + uid);
        } else {
          // Learning mode is OFF - reject unknown card
          display.clear();
          display.setFont(ArialMT_Plain_15);
          drawCenteredText("ACCESS DENIED", 0, 2);
          
          display.setFont(ArialMT_Plain_10);
          
          // Check if card is blocked/stolen/lost
          if (serverMessage.indexOf("stolen") >= 0 || 
              serverMessage.indexOf("lost") >= 0 || 
              serverMessage.indexOf("blocked") >= 0) {
            // Blocked card - show specific message
            display.drawString(0, 24, "CARD BLOCKED");
            display.drawString(0, 36, "UID: " + uid);
            display.drawString(0, 48, "Contact Admin");
            
            // More urgent feedback for blocked cards
            syncedBlinkBeep(RED_LED, 5, 100);
            Serial.println("BLOCKED: " + serverMessage + " - " + uid);
          } else {
            // Unknown card
            display.drawString(0, 24, "Unknown Card");
            display.drawString(0, 36, "UID: " + uid);
            display.drawString(0, 48, "Not Registered");
            
            // Standard error feedback - red blinks
            syncedBlinkBeep(RED_LED, 3, 150);
            Serial.println("REJECTED: Unknown card - " + uid);
          }
        }
      } else {
        display.drawString(0, 0, "Server Error");
        display.drawString(0, 16, serverMessage);
        display.drawString(0, 32, "UID: " + uid);
        display.drawString(0, 48, "Check Connection");
        syncedBlinkBeep(RED_LED, 2, 150);
        Serial.println("FAILED: " + serverMessage);
      }
      
      display.display();
    }

    delay(3000);
    rfid.PICC_HaltA();
  }

  displayIdleScreen();
  delay(1000);
}

// ================== IDLE SCREEN WITH WiFi BARS ==================
void displayIdleScreen() {
  display.clear();

  struct tm timeinfo;
  if (getLocalTime(&timeinfo)) {
    char buffer[15];
    strftime(buffer, sizeof(buffer), "%H:%M:%S", &timeinfo);

    // Check learning mode status (check every 10 seconds to avoid too many requests)
    static unsigned long lastLearningCheck = 0;
    static bool learningModeActive = false;
    unsigned long currentMillis = millis();
    
    if (currentMillis - lastLearningCheck > 10000) {
      learningModeActive = isLearningModeActive();
      lastLearningCheck = currentMillis;
    }

    if (learningModeActive) {
      // Show LEARNING MODE indicator
      drawCenteredText("LEARNING MODE", 0, 1);
      drawCenteredText("Ready to Detect", 12, 1);
      drawCenteredText("New Cards", 24, 1);
      drawCenteredText(buffer, 44, 2);
      
      // Blinking indicator
      if ((millis() / 500) % 2 == 0) {
        display.fillCircle(120, 4, 3);  // Blinking dot in top right
      }
    } else {
      // Normal idle screen
      drawCenteredText("Tryckasaken", 0, 2);
      drawCenteredText("Tap Driver Card", 24, 1);
      drawCenteredText(buffer, 40, 2);
    }

    // WiFi Signal Bars (always show at bottom)
    if (WiFi.status() == WL_CONNECTED) {
      int rssi = WiFi.RSSI();
      int bars = 0;

      if (rssi >= -50) bars = 4;
      else if (rssi >= -60) bars = 3;
      else if (rssi >= -70) bars = 2;
      else if (rssi >= -80) bars = 1;
      else bars = 0;

      int barWidth = 3;
      int spacing = 1;
      int baseX = 0;
      int baseY = 63;

      for (int i = 0; i < 4; i++) {
        int h = (i + 1) * 3;
        if (i < bars) {
          display.fillRect(baseX + i * (barWidth + spacing), baseY - h, barWidth, h);
        } else {
          display.drawRect(baseX + i * (barWidth + spacing), baseY - h, barWidth, h);
        }
      }
    } else {
      // Show "X" if no WiFi
      display.setFont(ArialMT_Plain_10);
      display.drawString(0, 53, "No WiFi");
    }

    display.display();
  }
}
