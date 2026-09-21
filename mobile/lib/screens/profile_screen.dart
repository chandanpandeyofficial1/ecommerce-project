import 'dart:io';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_client.dart';
import '../utils.dart';
import '../widgets/common.dart';

// Account details, profile photo and logout.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _busy = false;
  // True while a photo is uploading or being removed.
  bool _photoBusy = false;

  // Logs out; the auth gate then shows the login screen.
  Future<void> _logout() async {
    setState(() => _busy = true);
    await context.read<AuthProvider>().logout();
    if (mounted) setState(() => _busy = false);
  }

  // Opens the sheet with the photo choices.
  void _openPhotoSheet() {
    if (_photoBusy) return;
    final hasPhoto = context.read<AuthProvider>().user?.avatarUrl != null;
    showModalBottomSheet(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from gallery'),
              onTap: () {
                Navigator.pop(ctx);
                _pickAndUpload();
              },
            ),
            // Removing only makes sense when a photo exists.
            if (hasPhoto)
              ListTile(
                leading: const Icon(Icons.delete_outline),
                title: const Text('Remove photo'),
                onTap: () {
                  Navigator.pop(ctx);
                  _removePhoto();
                },
              ),
          ],
        ),
      ),
    );
  }

  // Picks a photo from the gallery, checks it and uploads it.
  Future<void> _pickAndUpload() async {
    if (_photoBusy) return;
    final auth = context.read<AuthProvider>();
    final picked = await ImagePicker().pickImage(
        source: ImageSource.gallery,
        imageQuality: 85,
        maxWidth: 1024,
        maxHeight: 1024);
    if (picked == null) return;
    final size = await File(picked.path).length();
    // Stop here with a message instead of calling the server.
    final problem = checkAvatarFile(picked.name, size);
    if (problem != null) {
      if (mounted) showMessage(context, problem);
      return;
    }
    setState(() => _photoBusy = true);
    try {
      await auth.uploadAvatar(picked.path, picked.name);
      if (mounted) showMessage(context, 'Profile photo updated');
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    }
    if (mounted) setState(() => _photoBusy = false);
  }

  // Removes the current photo.
  Future<void> _removePhoto() async {
    if (_photoBusy) return;
    final auth = context.read<AuthProvider>();
    setState(() => _photoBusy = true);
    try {
      await auth.removeAvatar();
      if (mounted) showMessage(context, 'Profile photo removed');
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    }
    if (mounted) setState(() => _photoBusy = false);
  }

  // The big round avatar with a camera button and an upload spinner.
  Widget _avatar(String? name, String? url) {
    // Letter circle, also used while the image loads or if it fails.
    final initial = CircleAvatar(
      radius: 40,
      child: Text(
          (name != null && name.isNotEmpty) ? name[0].toUpperCase() : '?',
          style: const TextStyle(fontSize: 32)),
    );
    return SizedBox(
      width: 88,
      height: 88,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned.fill(
            child: GestureDetector(
              onTap: _openPhotoSheet,
              child: Center(
                child: url == null
                    ? initial
                    : ClipOval(
                        child: CachedNetworkImage(
                          imageUrl: url,
                          width: 80,
                          height: 80,
                          fit: BoxFit.cover,
                          placeholder: (_, _) => initial,
                          errorWidget: (_, _, _) => initial,
                        ),
                      ),
              ),
            ),
          ),
          if (_photoBusy)
            const Positioned.fill(
              child: Center(
                child: SizedBox(
                    width: 80,
                    height: 80,
                    child: CircularProgressIndicator(strokeWidth: 3)),
              ),
            ),
          // Small camera button on the corner.
          Positioned(
            right: -4,
            bottom: -4,
            child: Material(
              color: Theme.of(context).colorScheme.primary,
              shape: const CircleBorder(),
              child: InkWell(
                customBorder: const CircleBorder(),
                onTap: _openPhotoSheet,
                child: const Padding(
                  padding: EdgeInsets.all(6),
                  child: Icon(Icons.camera_alt, size: 16, color: Colors.white),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Center(child: _avatar(user?.name, user?.avatarUrl)),
          const SizedBox(height: 24),
          Card(
            child: Column(
              children: [
                ListTile(
                    leading: const Icon(Icons.person_outline),
                    title: Text(user?.name ?? '')),
                ListTile(
                    leading: const Icon(Icons.email_outlined),
                    title: Text(user?.email ?? '')),
                ListTile(
                    leading: const Icon(Icons.phone_outlined),
                    title: Text(user?.phone ?? 'No phone saved')),
              ],
            ),
          ),
          const SizedBox(height: 24),
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(
                minimumSize: const Size.fromHeight(50)),
            onPressed: _busy ? null : _logout,
            icon: const Icon(Icons.logout),
            label: const Text('Logout'),
          ),
        ],
      ),
    );
  }
}
