import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Readonly field with a copy-to-clipboard action button.
class CopyField extends StatelessWidget {
  const CopyField({
    required this.value,
    required this.buttonLabel,
    required this.icon,
    super.key,
  });

  final String value;
  final String buttonLabel;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final scaffoldMessenger = ScaffoldMessenger.of(context);
    return Row(
      children: [
        Expanded(
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              border: Border.all(color: const Color(0xFFE2E8F0)),
              borderRadius: BorderRadius.circular(10),
            ),
            child: SelectableText(
              value,
              style: const TextStyle(color: Color(0xFF334155)),
            ),
          ),
        ),
        const SizedBox(width: 10),
        SizedBox(
          height: 44,
          child: ElevatedButton.icon(
            onPressed: () async {
              await Clipboard.setData(ClipboardData(text: value));
              scaffoldMessenger.showSnackBar(
                SnackBar(
                  content: Text('$buttonLabel copié !'),
                  behavior: SnackBarBehavior.floating,
                ),
              );
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFD7EBF9),
              foregroundColor: const Color(0xFF0F172A),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            icon: Icon(icon),
            label: Text(buttonLabel),
          ),
        ),
      ],
    );
  }
}
