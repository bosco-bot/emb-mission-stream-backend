/// Utilitaires pour le mapping des pays
class CountryUtils {
  CountryUtils._();

  /// Mapping des noms de pays vers les codes ISO 3166-1 alpha-2
  static const Map<String, String> countryNameToCode = {
    // Pays africains (les plus fréquents)
    'Benin': 'bj',
    'Burkina Faso': 'bf',
    'Togo': 'tg',
    'Niger': 'ne',
    'Mali': 'ml',
    'Senegal': 'sn',
    'Ghana': 'gh',
    'Nigeria': 'ng',
    'Cote d\'Ivoire': 'ci',
    'Côte d\'Ivoire': 'ci',
    'Cameroon': 'cm',
    'Gabon': 'ga',
    'Congo': 'cg',
    'DRC': 'cd',
    'Democratic Republic of Congo': 'cd',
    'Morocco': 'ma',
    'Algeria': 'dz',
    'Tunisia': 'tn',
    'Egypt': 'eg',
    'Ethiopia': 'et',
    'Kenya': 'ke',
    'Tanzania': 'tz',
    'South Africa': 'za',
    'Madagascar': 'mg',
    
    // Pays européens
    'France': 'fr',
    'United Kingdom': 'gb',
    'Germany': 'de',
    'Spain': 'es',
    'Italy': 'it',
    'Portugal': 'pt',
    'Belgium': 'be',
    'Netherlands': 'nl',
    'Switzerland': 'ch',
    
    // Amériques
    'United States': 'us',
    'USA': 'us',
    'Canada': 'ca',
    'Brazil': 'br',
    'Argentina': 'ar',
    'Mexico': 'mx',
    
    // Asie
    'China': 'cn',
    'Japan': 'jp',
    'India': 'in',
    'Thailand': 'th',
    'Vietnam': 'vn',
    
    // Autres
    'Australia': 'au',
    'Turkey': 'tr',
  };

  /// Convertit un nom de pays en code ISO 3166-1 alpha-2
  /// Retourne 'unknown' si le pays n'est pas trouvé
  static String countryNameToIsoCode(String countryName) {
    return countryNameToCode[countryName] ?? 
           countryNameToCode[countryName.toLowerCase()] ?? 
           'unknown';
  }

  /// Vérifie si un code pays est connu
  static bool isKnownCountry(String countryName) {
    return countryNameToCode.containsKey(countryName) || 
           countryNameToCode.containsKey(countryName.toLowerCase());
  }
}


