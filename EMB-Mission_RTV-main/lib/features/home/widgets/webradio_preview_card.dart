import 'package:audioplayers/audioplayers.dart';
import 'package:emb_mission_dashboard/core/services/azuracast_service.dart';
import 'package:emb_mission_dashboard/core/shared/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';

class WebRadioPreviewCard extends StatefulWidget {
  const WebRadioPreviewCard({super.key});

  @override
  State<WebRadioPreviewCard> createState() => _WebRadioPreviewCardState();
}

class _WebRadioPreviewCardState extends State<WebRadioPreviewCard> {
  String _title = 'Chargement...';
  String _artist = '';
  String _duration = '--:--';
  bool _isLive = false;
  bool _isLoading = true;
  late AudioPlayer _audioPlayer;
  bool _isPlayerPlaying = false;

  @override
  void initState() {
    super.initState();
    _initAudioPlayer();
    _loadNowPlaying();
    _startPeriodicRefresh();
  }

  @override
  void dispose() {
    _audioPlayer.dispose();
    super.dispose();
  }

  void _startPeriodicRefresh() {
    // Rafraîchir les données toutes les 15 secondes (plus long pour éviter le rate limit)
    Future.delayed(const Duration(seconds: 15), () {
      if (mounted) {
        _loadNowPlaying(silent: true); // Refresh silencieux sans loader
        _startPeriodicRefresh();
      }
    });
  }

  void _initAudioPlayer() {
    _audioPlayer = AudioPlayer();
    _audioPlayer.onPlayerStateChanged.listen((PlayerState state) {
      if (mounted) {
        setState(() {
          _isPlayerPlaying = state == PlayerState.playing;
        });
      }
    });
  }

  Future<void> _togglePlayPause() async {
    if (_isPlayerPlaying) {
      await _audioPlayer.stop();
    } else {
      try {
        // URL de streaming de la radio (HTTPS publique) - détection automatique du domaine
        final streamUrl = 'https://${Uri.base.host}/stream';
        await _audioPlayer.play(UrlSource(streamUrl));
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Erreur de lecture: $e'),
              backgroundColor: const Color(0xFFEF4444),
              duration: const Duration(seconds: 3),
            ),
          );
        }
      }
    }
  }

  Future<void> _loadNowPlaying({bool silent = false}) async {
    try {
      final nowPlaying = await AzuraCastService.instance.getNowPlaying();
      if (mounted) {
        setState(() {
          _title = nowPlaying.nowPlaying.song.title.isNotEmpty 
              ? nowPlaying.nowPlaying.song.title 
              : 'Aucun titre';
          _artist = nowPlaying.nowPlaying.song.artist.isNotEmpty 
              ? nowPlaying.nowPlaying.song.artist 
              : 'Aucun artiste';
          _duration = _formatDuration(nowPlaying.nowPlaying.duration);
          _isLive = nowPlaying.live.isLive;
          if (!silent) {
            _isLoading = false;
          }
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          if (!silent) {
            // Seulement afficher l'erreur au chargement initial
            _title = 'Hors ligne';
            _artist = 'Radio actuellement hors ligne';
            _duration = '--:--';
            _isLive = false;
            _isLoading = false;
          }
          // En refresh silencieux, on garde les anciennes données
        });
      }
    }
  }

  String _formatDuration(int seconds) {
    final minutes = seconds ~/ 60;
    final secs = seconds % 60;
    return '${minutes.toString()}:${secs.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.1),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: AppTheme.redColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Icon(Icons.radio, color: AppTheme.redColor, size: 16),
              ),
              const Gap(8),
              const Text(
                'Aperçu Lecteur WebRadio',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
          ),
          const Gap(24),
          _isLoading
              ? const CircularProgressIndicator()
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _title,
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    Text(
                      _artist,
                      style: const TextStyle(
                        color: Color(0xFF64748B),
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
          const Gap(16),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Row(
              children: [
                GestureDetector(
                  onTap: _togglePlayPause,
                  child: Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      color: _isPlayerPlaying 
                          ? AppTheme.redColor 
                          : const Color(0xFF38BDF8),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      _isPlayerPlaying ? Icons.stop : Icons.play_arrow,
                      color: Colors.white,
                      size: 18,
                    ),
                  ),
                ),
                const Gap(12),
                Expanded(
                  child: _isPlayerPlaying 
                      ? const _WaveformVisualizer(isAnimated: true)
                      : const _WaveformVisualizer(isAnimated: false),
                ),
                const Gap(12),
                Text(
                  _duration,
                  style: const TextStyle(color: Color(0xFF64748B)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _WaveformVisualizer extends StatefulWidget {
  const _WaveformVisualizer({this.isAnimated = false});
  
  final bool isAnimated;
  
  @override
  State<_WaveformVisualizer> createState() => _WaveformVisualizerState();
}

class _WaveformVisualizerState extends State<_WaveformVisualizer>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 800),
    );
    
    if (widget.isAnimated) {
      _controller.repeat(reverse: true);
    }
  }

  @override
  void didUpdateWidget(_WaveformVisualizer oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.isAnimated && !_controller.isAnimating) {
      _controller.repeat(reverse: true);
    } else if (!widget.isAnimated && _controller.isAnimating) {
      _controller.stop();
      _controller.reset();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 24,
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, child) {
          return Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: List.generate(
              30,
              (index) {
                final baseHeight = 3.0 + (index % 8) * 1.5;
                final animatedHeight = widget.isAnimated 
                    ? baseHeight * (0.5 + _controller.value * 0.5)
                    : baseHeight;
                return Container(
                  width: 2,
                  height: animatedHeight,
                  decoration: BoxDecoration(
                    color: widget.isAnimated 
                        ? AppTheme.redColor.withValues(alpha: 0.7)
                        : const Color(0xFFCBD5E1),
                    borderRadius: BorderRadius.circular(1),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
