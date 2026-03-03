import 'package:flutter/material.dart';
import 'package:gap/gap.dart';

class HomeFooter extends StatelessWidget {
  const HomeFooter({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      color: Colors.white,
      child: const Column(
        children: [
          Gap(20),
          Text(
            '© 2025 EMB-MISSION-RTV. Tous droits réservés.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Color(0xFF64748B),
              fontSize: 14,
            ),
          ),
          Gap(20),
        ],
      ),
    );
  }
}
