import 'package:flutter/foundation.dart';

/// Data model for a media item displayed in the contents list.
@immutable
class ContentItem {
  const ContentItem({
    required this.thumbnail,
    required this.title,
    required this.subtitle,
    required this.duration,
    required this.date,
  });

  final String thumbnail;
  final String title;
  final String subtitle;
  final String duration;
  final String date;
}
