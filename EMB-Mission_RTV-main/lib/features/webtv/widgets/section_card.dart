import 'package:flutter/material.dart';

/// A rounded, elevated container used to group WebTV page sections.
class SectionCard extends StatelessWidget {
  const SectionCard({
    required this.child,
    super.key,
  });

  /// The content of the card.
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: const [
          BoxShadow(
            color: Color(0x14000000),
            blurRadius: 12,
            offset: Offset(0, 4),
          ),
        ],
      ),
      padding: const EdgeInsets.all(16),
      child: child,
    );
  }
}
