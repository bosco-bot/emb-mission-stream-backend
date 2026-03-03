import 'package:flutter/material.dart';
import 'package:gap/gap.dart';

class TabPill extends StatelessWidget {
  const TabPill({
    required this.icon,
    required this.label,
    this.selected = false,
    super.key,
  });
  final IconData icon;
  final String label;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: selected ? const Color(0xFF111827) : Colors.transparent,
        borderRadius: BorderRadius.circular(10),
        border: selected ? null : Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        children: [
          Icon(
            icon,
            size: 18,
            color: selected ? Colors.white : const Color(0xFF111827),
          ),
          const Gap(8),
          Text(
            label,
            style: TextStyle(
              fontWeight: FontWeight.w600,
              color: selected ? Colors.white : const Color(0xFF111827),
            ),
          ),
        ],
      ),
    );
  }
}
