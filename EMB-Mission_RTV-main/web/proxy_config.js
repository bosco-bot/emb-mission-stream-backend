// Configuration proxy pour éviter les problèmes CORS en développement
// Ce fichier peut être utilisé avec un serveur de développement qui supporte les proxies

const PROXY_CONFIG = {
  "/api/*": {
    "target": "https://radio.embmission.com",
    "secure": true,
    "changeOrigin": true,
    "logLevel": "debug"
  }
};

module.exports = PROXY_CONFIG;
























