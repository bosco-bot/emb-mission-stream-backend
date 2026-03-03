import 'package:emb_mission_dashboard/features/contents/widgets/content_item.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';

class ContentsListCard extends StatelessWidget {
  const ContentsListCard({
    required this.items,
    super.key,
  });
  final List<ContentItem> items;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        children: [
          // Header row
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: const BoxDecoration(
              color: Color(0xFFF9FAFB),
              borderRadius: BorderRadius.vertical(top: Radius.circular(12)),
            ),
            child: const Row(
              children: [
                Expanded(flex: 6, child: _HeaderLabel('Nom')),
                Expanded(flex: 2, child: _HeaderLabel('Durée')),
                Expanded(flex: 2, child: _HeaderLabel("Date d'ajout")),
                SizedBox(width: 80, child: _HeaderLabel('Actions')),
              ],
            ),
          ),

          for (final item in items) _ContentRow(item: item),

          // Footer
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            child: _CardFooter(),
          ),
        ],
      ),
    );
  }
}

class _HeaderLabel extends StatelessWidget {
  const _HeaderLabel(this.text);
  final String text;
  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: const TextStyle(
        color: Color(0xFF6B7280),
        fontWeight: FontWeight.w600,
      ),
    );
  }
}

class _ContentRow extends StatelessWidget {
  const _ContentRow({required this.item});
  final ContentItem item;
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: const BoxDecoration(
        border: Border(
          top: BorderSide(color: Color(0xFFF3F4F6)),
        ),
      ),
      child: Row(
        children: [
          // Title cell
          Expanded(
            flex: 6,
            child: Row(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.network(
                    item.thumbnail,
                    width: 96,
                    height: 56,
                    fit: BoxFit.cover,
                  ),
                ),
                const Gap(12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.title,
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF111827),
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                      Text(
                        item.subtitle,
                        style: const TextStyle(color: Color(0xFF9CA3AF)),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          // Duration
          Expanded(
            flex: 2,
            child: Text(
              item.duration,
              style: const TextStyle(color: Color(0xFF111827)),
            ),
          ),
          // Date
          Expanded(
            flex: 2,
            child: Text(
              item.date,
              style: const TextStyle(color: Color(0xFF111827)),
            ),
          ),
          // Actions
          const SizedBox(
            width: 80,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                Icon(Icons.edit_outlined, size: 18, color: Color(0xFF6B7280)),
                Gap(10),
                Icon(
                  Icons.play_arrow_rounded,
                  size: 20,
                  color: Color(0xFF6B7280),
                ),
                Gap(10),
                Icon(Icons.delete_outline, size: 18, color: Color(0xFFEF4444)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CardFooter extends StatelessWidget {
  const _CardFooter();
  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Expanded(
          child: Text(
            'Affiche 1-3 sur 15',
            style: TextStyle(color: Color(0xFF6B7280)),
          ),
        ),
        OutlinedButton(
          onPressed: () {},
          style: OutlinedButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          ),
          child: const Text('Précédent'),
        ),
        const Gap(10),
        OutlinedButton(
          onPressed: () {},
          style: OutlinedButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          ),
          child: const Text('Suivant'),
        ),
      ],
    );
  }
}
