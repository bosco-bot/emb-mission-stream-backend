import 'package:emb_mission_dashboard/core/widgets/topbar_widgets.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';

class ContentsTopBar extends StatelessWidget {
  const ContentsTopBar({super.key});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        // Search
        Expanded(
          child: Container(
            height: 40,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            decoration: BoxDecoration(
              color: const Color(0xFFF3F4F6),
              borderRadius: BorderRadius.circular(20),
            ),
            child: const Row(
              children: [
                Icon(Icons.search, color: Color(0xFF9CA3AF)),
                Gap(8),
                Expanded(
                  child: Text(
                    'Rechercher un contenu…',
                    style: TextStyle(color: Color(0xFF9CA3AF)),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ),
        ),
        const Gap(16),
        // Start Live
        const StartLiveButton(),
        const Gap(10),
        // User chip
        const UserProfileChip(),
      ],
    );
  }
}
