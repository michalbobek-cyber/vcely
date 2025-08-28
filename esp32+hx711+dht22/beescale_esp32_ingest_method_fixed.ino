/*
  BeeScale – ESP32 DevKit V1 (AP+STA + HTTP/HTTPS + GET/POST + JSON + Auth headers + DIAG)
  ----------------------------------------------------------------------------------------
  Board: ESP32 Dev Module
  Sensors: HX711 (DT=25, SCK=26), DHT22 (DATA=27)
*/

#include <Arduino.h>
#include <WiFi.h>
#include <WebServer.h>
#include <Preferences.h>
#include "HX711.h"
#include <WiFiClientSecure.h>
#include <DHT.h>

// Pins
#define HX_DT     25
#define HX_SCK    26
#define DHTPIN    27
#define DHTTYPE   DHT22
#define TARE_BTN  13

HX711 scale;
DHT dht(DHTPIN, DHTTYPE);
WebServer server(80);
Preferences prefs;

// Config
String   c_ssid, c_pass, c_host, c_path, c_api, c_api_param, c_device_id;
uint16_t c_port   = 443;
bool     c_https  = true;
bool     c_post   = true;
bool     c_json   = false; // POST content-type
// auth: 0=none (param), 1=X-API-Key, 2=Bearer
uint8_t  c_auth   = 0;

float    cal_factor = 420.0f;
long     tare_offset = 0;
uint32_t period_ms   = 5UL*60UL*1000UL; // 5 min

// Stats
int last_http_code = 0;
int last_bytes = 0;
String last_msg = "";
unsigned long last_send_ms = 0;
String last_preview = "";
String last_body = "";

// Helpers
String htmlHeader(){
  return F("<!doctype html><meta name=viewport content='width=device-width,initial-scale=1'>"
           "<style>body{font:16px/1.45 system-ui,Segoe UI,Arial;padding:16px}"
           "label{display:block;margin:.35em 0}input,select{width:300px;max-width:95%}button{padding:.5em 1em}"
           ".row{display:flex;gap:12px;flex-wrap:wrap}.card{padding:12px;border:1px solid #ddd;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.06)}"
           "code,pre{background:#f6f6f6;padding:6px;border-radius:6px;display:block;white-space:pre-wrap;max-width:100%;overflow:auto}</style>");
}
long hxReadAvg(uint8_t n=5){ long s=0; for(uint8_t i=0;i<n;i++) s+=scale.read(); return s/(long)n; }
float readTemp(){ return dht.readTemperature(); }
float readHum(){  return dht.readHumidity(); }
float weightG(){ long r=hxReadAvg(5); return (float)(r - tare_offset) * cal_factor; }

void loadCfg(){
  prefs.begin("cfg", true);
  c_ssid   = prefs.getString("ssid","");
  c_pass   = prefs.getString("pass","");
  c_host   = prefs.getString("host","");
  c_path   = prefs.getString("path","/vcely/api/ingest");
  c_api    = prefs.getString("api","");
  c_api_param = prefs.getString("apip","key");
  c_device_id = prefs.getString("devid","");
  c_port   = prefs.getUShort("port",443);
  c_https  = prefs.getBool("https", true);
  c_post   = prefs.getBool("post",  true);
  c_json   = prefs.getBool("json",  false);
  c_auth   = prefs.getUChar("auth", 0);
  cal_factor = prefs.getFloat("cal",420.0f);
  tare_offset= prefs.getLong("tare",0);
  period_ms  = prefs.getUInt("period", 5UL*60UL*1000UL);
  prefs.end();
}
void saveCfg(){
  prefs.begin("cfg", false);
  prefs.putString("ssid", c_ssid);
  prefs.putString("pass", c_pass);
  prefs.putString("host", c_host);
  prefs.putString("path", c_path);
  prefs.putString("api",  c_api);
  prefs.putString("apip", c_api_param);
  prefs.putString("devid",c_device_id);
  prefs.putUShort("port", c_port);
  prefs.putBool("https",  c_https);
  prefs.putBool("post",   c_post);
  prefs.putBool("json",   c_json);
  prefs.putUChar("auth",  c_auth);
  prefs.putFloat("cal",   cal_factor);
  prefs.putLong("tare",   tare_offset);
  prefs.putUInt("period", period_ms);
  prefs.end();
}
String chipId(){ uint64_t id = ESP.getEfuseMac(); char b[17]; snprintf(b,sizeof(b), "%04X%08X",(uint16_t)(id>>32),(uint32_t)id); return String(b); }

// Web UI
void handleRoot(){
  float t=readTemp(), h=readHum(), w=weightG();
  String s=htmlHeader();
  s+=F("<h3>BeeScale – ESP32 (HTTP/HTTPS, GET/POST, JSON, Auth)</h3><div class='row'><div class='card'><form action='/save' method='get'>");
  s+="SSID: <input name='ssid' value='"+c_ssid+"'><br>";
  s+="Heslo: <input type='password' name='pass' value='"+c_pass+"'><br>";
  s+="Host: <input name='host' value='"+c_host+"'><br>";
  s+="Port: <input name='port' value='"+String(c_port)+"'><br>";
  s+="Path: <input name='path' value='"+c_path+"'><br>";
  s+="API key: <input name='api' value='"+c_api+"'><br>";
  s+="API param: <select name='apip'><option"; if(c_api_param=="key") s+=" selected"; s+=">key</option><option"; if(c_api_param=="api_key") s+=" selected"; s+=">api_key</option><option"; if(c_api_param=="token") s+=" selected"; s+=">token</option></select><br>";
  s+="Auth header: <select name='auth'><option value='none'"; if(c_auth==0) s+=" selected"; s+=">none</option><option value='xapi'"; if(c_auth==1) s+=" selected"; s+=">X-API-Key</option><option value='bearer'"; if(c_auth==2) s+=" selected"; s+=">Authorization: Bearer</option></select><br>";
  s+="Method: <select name='method'><option"; if(!c_post) s+=" selected"; s+=">GET</option><option"; if(c_post) s+=" selected"; s+=">POST</option></select><br>";
  s+="POST type: <select name='ptype'><option value='form'"; if(!c_json) s+=" selected"; s+=">x-www-form-urlencoded</option><option value='json'"; if(c_json) s+=" selected"; s+=">application/json</option></select><br>";
  s+="Device ID (volitelné): <input name='devid' value='"+c_device_id+"'><br>";
  s+="Period [min]: <input name='period' value='"+String((float)period_ms/60000.0f,2)+"'><br>";
  s+=F("<button>Uložit</button></form>");
  s+=F("<p><a href='/tare'>Tare</a> | <a href='/setk?val=420.0'>SetK</a> | <a href='/sendNow'>Odeslat teď</a> | <a href='/diag'>DIAG</a></p></div>");
  s+=F("<div class='card'>");
  s+="Device: "+chipId();
  s+="<br>STA: "+WiFi.SSID()+" / "+WiFi.localIP().toString();
  s+="<br>AP: BeeScale32 / 192.168.4.1";
  s+="<hr>W="+String(w,1)+" g, T="+String(t,1)+" &deg;C, H="+String(h,0)+" %";
  if(last_send_ms){ s+="<hr>Last send: HTTP "+String(last_http_code)+" ("+String(last_bytes)+" B) – "+last_msg; }
  s+="</div></div>";
  server.send(200,"text/html",s);
}
void handleSave(){
  if(server.hasArg("ssid")) c_ssid=server.arg("ssid");
  if(server.hasArg("pass")) c_pass=server.arg("pass");
  if(server.hasArg("host")) c_host=server.arg("host");
  if(server.hasArg("port")) c_port=server.arg("port").toInt();
  if(server.hasArg("path")) c_path=server.arg("path");
  if(server.hasArg("api"))  c_api =server.arg("api");
  if(server.hasArg("apip")) c_api_param=server.arg("apip");
  if(server.hasArg("devid"))c_device_id=server.arg("devid");
  c_https = server.hasArg("https") || (c_port==443);
  if(server.hasArg("auth")){
    String a=server.arg("auth");
    if(a=="xapi") c_auth=1; else if(a=="bearer") c_auth=2; else c_auth=0;
  }
  if(server.hasArg("method")) c_post = (server.arg("method")=="POST");
  if(server.hasArg("ptype"))  c_json = (server.arg("ptype")=="json");
  if(server.hasArg("period")){ float m=server.arg("period").toFloat(); if(m<0.1f)m=0.1f; if(m>1440)m=1440; period_ms=(uint32_t)(m*60000.0f); }
  saveCfg();
  if(c_ssid.length()) WiFi.begin(c_ssid.c_str(), c_pass.c_str());
  server.send(200,"text/html",F("Uloženo. <a href='/'>Zpět</a>"));
}
long hxReadAvgN(uint8_t n){ long s=0; for(uint8_t i=0;i<n;i++) s+=scale.read(); return s/(long)n; }
void handleTare(){ tare_offset=hxReadAvgN(10); saveCfg(); server.send(200,"text/plain","OK"); }
void handleSetK(){ if(server.hasArg("val")){ cal_factor=server.arg("val").toFloat(); saveCfg(); } server.send(200,"text/plain","OK"); }

// HTTP send
int httpSendOnce(){
  last_bytes=0; last_msg=""; last_preview=""; last_body="";
  if(!c_host.length() || !c_path.length()) { last_msg="Missing host/path"; return -1; }

  float t=readTemp(), h=readHum(), w=weightG();
  String path=c_path; if(path[0]!='/') path="/"+path;

  String query="";
  if(c_auth==0){ // key as parameter
    query += c_api_param + "="+c_api;
  }
  if(c_device_id.length()){
    if(query.length()) query += "&";
    query += "device_id=" + c_device_id;
  }
  String wth = String("&weight_g=")+String(w,1)+"&temp_c="+String(t,1)+"&hum_pct="+String(h,0);

  String reqline, headers, payload;
  if(c_post){
    if(c_json){
      String json="{";
      json += "\"weight_g\":"+String(w,1)+",";
      json += "\"temp_c\":"+String(t,1)+",";
      json += "\"hum_pct\":"+String(h,0);
      if(c_auth==0){ json += ",\""+c_api_param+"\":\""+c_api+"\""; }
      if(c_device_id.length()){ json += ",\"device_id\":\""+c_device_id+"\""; }
      json += "}";
      payload=json;
      reqline = "POST " + path + " HTTP/1.1\r\n";
      headers = "Host: " + c_host + "\r\nUser-Agent: BeeScale/1.0\r\nContent-Type: application/json\r\nContent-Length: " + String(payload.length()) + "\r\nConnection: close\r\n";
    } else {
      String body = query + wth;
      if(query.length()==0) body = wth.substring(1);
      payload=body;
      reqline = "POST " + path + " HTTP/1.1\r\n";
      headers = "Host: " + c_host + "\r\nUser-Agent: BeeScale/1.0\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: " + String(payload.length()) + "\r\nConnection: close\r\n";
    }
    headers += "\r\n";
  } else {
    String fullq = query + wth;
    if(query.length()==0) fullq = wth.substring(1);
    reqline = "GET " + path + "?" + fullq + " HTTP/1.1\r\n";
    headers = "Host: " + c_host + "\r\nUser-Agent: BeeScale/1.0\r\nConnection: close\r\n\r\n";
  }

  // auth headers
  if(c_auth==1){ // X-API-Key
    int pos=headers.indexOf("\r\n\r\n");
    headers = headers.substring(0,pos) + "\r\nX-API-Key: " + c_api + headers.substring(pos);
  } else if(c_auth==2){ // Bearer
    int pos=headers.indexOf("\r\n\r\n");
    headers = headers.substring(0,pos) + "\r\nAuthorization: Bearer " + c_api + headers.substring(pos);
  }

  last_preview = reqline + headers + payload;

  // client
  if(c_https || c_port==443){
    WiFiClientSecure c; c.setInsecure(); c.setTimeout(8000);
    if(!c.connect(c_host.c_str(), c_port)){ last_msg="TLS connect failed"; return -2; }
    c.print(reqline); c.print(headers); if(c_post) c.print(payload);
    String status = c.readStringUntil('\n');
    if(status.startsWith("HTTP/")){ int sp=status.indexOf(' '), sp2=status.indexOf(' ',sp+1); last_http_code=status.substring(sp+1,sp2).toInt(); } else last_http_code=0;
    // headers
    while(c.connected()){
      String line=c.readStringUntil('\n');
      if(line=="\r" || line.length()==0) break;
    }
    // body
    char buf[129]; int total=0;
    while(c.available()){
      int n=c.readBytes(buf,sizeof(buf)-1); if(n<=0) break;
      buf[n]=0; total += n;
      if(last_body.length()<512) last_body += String(buf);
    }
    last_bytes=total; c.stop();
  } else {
    WiFiClient c; c.setTimeout(8000);
    if(!c.connect(c_host.c_str(), c_port)){ last_msg="TCP connect failed"; return -2; }
    c.print(reqline); c.print(headers); if(c_post) c.print(payload);
    String status = c.readStringUntil('\n');
    if(status.startsWith("HTTP/")){ int sp=status.indexOf(' '), sp2=status.indexOf(' ',sp+1); last_http_code=status.substring(sp+1,sp2).toInt(); } else last_http_code=0;
    while(c.connected()){
      String line=c.readStringUntil('\n');
      if(line=="\r" || line.length()==0) break;
    }
    char buf[129]; int total=0;
    while(c.available()){
      int n=c.readBytes(buf,sizeof(buf)-1); if(n<=0) break;
      buf[n]=0; total += n;
      if(last_body.length()<512) last_body += String(buf);
    }
    last_bytes=total; c.stop();
  }
  if(last_http_code>=200 && last_http_code<300) last_msg="OK";
  else if(last_http_code==301 || last_http_code==302) last_msg="Redirect";
  else if(last_http_code==400) last_msg="Bad Request (params/format)";
  else if(last_http_code==401) last_msg="Unauthorized (API key/auth)";
  else if(last_http_code==405) last_msg="Method Not Allowed";
  else if(last_http_code==0)   last_msg="No HTTP status";
  else last_msg="HTTP "+String(last_http_code);
  return last_http_code;
}

void handleSendNow(){ last_send_ms=millis(); int code=httpSendOnce(); server.send(200,"text/plain", String("HTTP ")+code+" / "+last_msg+" ("+String(last_bytes)+" B)"); }
void handleDiag(){
  String s=htmlHeader();
  s+=F("<h3>DIAG</h3>");
  s+="Host: "+c_host+":"+String(c_port)+" "+(c_https?"(HTTPS)":"(HTTP)")+" — Method: "+(c_post?"POST":"GET")+" — POST type: "+(c_json?"JSON":"FORM")+"<br>";
  s+="Auth: "; s+=(c_auth==0?"param":(c_auth==1?"X-API-Key":"Bearer")); s+=" — API param: "+c_api_param+" — DeviceID: "+(c_device_id.length()?c_device_id:"(none)")+"<br>";
  s+="STA: "+WiFi.SSID()+" / "+WiFi.localIP().toString()+"<br>";
  s+="<a href='/sendNow'>sendNow</a><hr>";
  s+="<b>Request preview</b><pre>"+last_preview+"</pre>";
  s+="<hr><b>Last send</b>: HTTP "+String(last_http_code)+" ("+String(last_bytes)+" B) – "+last_msg;
  if(last_body.length()) s+="<h4>Response body (first 512 chars)</h4><pre>"+last_body+"</pre>";
  server.send(200,"text/html",s);
}

// Setup/loop
unsigned long lastSend=0;
void setup(){
  dht.begin();
  scale.begin(HX_DT, HX_SCK);
  delay(200);
  loadCfg();
  if(tare_offset==0) tare_offset=hxReadAvg(10);
  pinMode(TARE_BTN, INPUT_PULLUP);

  WiFi.mode(WIFI_AP_STA);
  WiFi.softAP("BeeScale32","beescale123");
  if(c_ssid.length()) WiFi.begin(c_ssid.c_str(), c_pass.c_str());

  server.on("/", handleRoot);
  server.on("/save", handleSave);
  server.on("/tare", handleTare);
  server.on("/setk", handleSetK);
  server.on("/sendNow", handleSendNow);
  server.on("/diag", handleDiag);
  server.begin();

  lastSend = millis();
}
void loop(){
  server.handleClient();
  if (millis()-lastSend >= period_ms){
    lastSend=millis();
    last_send_ms=millis();
    last_http_code=httpSendOnce();
  }
  static uint8_t p=HIGH; uint8_t c=digitalRead(TARE_BTN);
  if(p==HIGH && c==LOW){ tare_offset=hxReadAvg(10); saveCfg(); }
  p=c;
}
