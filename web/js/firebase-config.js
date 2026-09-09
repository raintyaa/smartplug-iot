// Firebase Realtime Database Configuration for RANOVA Smart Plug
const FIREBASE_CONFIG = {
  databaseURL: "https://smartplug-4442d-default-rtdb.asia-southeast1.firebasedatabase.app"
};

if (typeof module !== "undefined" && module.exports) {
  module.exports = FIREBASE_CONFIG;
} else {
  window.FIREBASE_CONFIG = FIREBASE_CONFIG;
}
