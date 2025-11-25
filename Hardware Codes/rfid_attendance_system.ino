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
const char* serverURL = "http://192.168.1.100/tryckasaken/pages/driver/rfid-attendance-handler.php";
// Replace 192.168.1.100 with your actual server IP address

// ================== WiFi NETWORKS ==================
struct WiFiNetwork {
  const char* ssid;
  const char* password;
};

WiFiNetwork networks[] = {
  {"KAY DIDAY ITO", "Did@ylangsakalam01"},
  {"Jerick", "onetwo82"},
  {"Vincent's Iphone", "123456789"},
  {"Bluetooth", "onetoeight"},
  {"JC", "12345678"},
};

int totalNetworks = sizeof(networks) / sizeof(networks[0]);

// ================== LOCAL CARD DATABASE (FALLBACK) ==================
struct Card {
  const char* uid;
  const char* name;
  int tapCount;  // 0 = ready for first tap, 1 = ready for second tap
};

Card cards[] = {
  {"E317A32A", "Jc jade Nealega", 0}
};

int totalCards = sizeof(cards) / sizeof(cards[0]);

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

// ================== FIND CARD IN LOCAL DATABASE ==================
int getCardIndex(String uid) {
  for (int i = 0; i < totalCards; i++) {
    if (uid.equals(cards[i].uid)) return i;
  }
  return -1;
}

// ================== SEND RFID DATA TO SERVER ==================
bool sendToServer(String uid, String action, String &serverMessage) {
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

    int cardIndex = getCardIndex(uid);
    display.clear();

    if (cardIndex != -1) {
      Card &driver = cards[cardIndex];

      // Determine action based on tap count
      // First tap (tapCount = 0): send "online" action
      // Second tap (tapCount = 1): send "offline" action
      String action = (driver.tapCount == 0) ? "online" : "offline";
      
      // Show processing
      display.clear();
      drawCenteredText("Processing...", 20, 2);
      display.display();

      // Send to server
      String serverMessage = "";
      bool serverSuccess = sendToServer(uid, action, serverMessage);

      if (serverSuccess) {
        // Update tap count for next tap
        driver.tapCount = (driver.tapCount == 0) ? 1 : 0;
        
        String status = (action == "online") ? "Going Online" : "Going Offline";
        syncedBlinkBeep(GREEN_LED, (action == "online") ? 1 : 2, 150);

        struct tm timeinfo;
        getLocalTime(&timeinfo);

        char buffer[30];
        strftime(buffer, sizeof(buffer), "%b %d, %Y %I:%M:%S %p", &timeinfo);

        display.clear();
        display.setFont(ArialMT_Plain_10);
        display.drawString(0, 0, String(driver.name));
        display.drawString(0, 16, "Status: " + status);
        display.drawString(0, 32, buffer);
        display.drawString(0, 48, "Server: " + serverMessage);
        display.display();

        Serial.println("SUCCESS: " + serverMessage);

      } else {
        // Server failed - don't update tap count
        display.clear();
        display.setFont(ArialMT_Plain_10);
        display.drawString(0, 0, "Server Error");
        display.drawString(0, 16, serverMessage);
        display.drawString(0, 32, "UID: " + uid);
        display.drawString(0, 48, "Check Connection");
        display.display();

        syncedBlinkBeep(RED_LED, 3, 150);
        
        Serial.println("FAILED: " + serverMessage);
      }

    } else {
      // Unknown card
      display.clear();
      display.setFont(ArialMT_Plain_10);
      display.drawString(0, 0, "Unknown Card");
      display.drawString(0, 16, "Access Denied");
      display.drawString(0, 32, "UID: " + uid);
      display.drawString(0, 48, "Not Registered");
      display.display();

      syncedBlinkBeep(RED_LED, 2, 150);
      
      Serial.println("Unknown card: " + uid);
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

    drawCenteredText("Tryckasaken", 0, 2);
    drawCenteredText("Tap Driver Card", 24, 1);
    drawCenteredText(buffer, 40, 2);

    // WiFi Signal Bars
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
