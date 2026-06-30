# A. Product Requirement Document

## Tujuan Aplikasi

ClipTrend AI membantu creator, brand, agency, dan tim social media mengubah video panjang menjadi video pendek vertikal yang siap dipakai di YouTube Shorts, TikTok, dan Instagram Reels.

Tujuan utama:

- Mempercepat proses repurpose long-form video.
- Menemukan momen terbaik secara otomatis.
- Mendeteksi niche/kategori sebelum rendering.
- Membuat subtitle, hook text, title, caption, hashtag, dan keyword.
- Menyiapkan render 9:16 siap download.

## Target User

1. Creator YouTube/podcast.
2. Agency social media.
3. Brand marketing team.
4. Event organizer.
5. Edukator dan content seller.
6. Tim internal perusahaan yang ingin membuat konten pendek dari dokumentasi panjang.

## Fitur Utama

- Auth register/login/logout.
- Role admin/user.
- User dashboard.
- Project video per user.
- Upload video private.
- Authorized YouTube URL metadata placeholder.
- AI niche detection.
- Topic, audience, style, recommendation.
- AI clip candidate generation.
- Viral score, retention score, emotional hook.
- Subtitle generation dan editor.
- Trend checker modular.
- Render queue FFmpeg.
- Output library.
- Admin dashboard.
- Error logs dan retry render.

## User Flow

1. User login.
2. User membuat project.
3. User upload video.
4. Sistem menyimpan metadata video.
5. Queue menjalankan analisis AI.
6. Sistem menampilkan niche, alasan, audience, output recommendation.
7. Sistem membuat beberapa kandidat clip.
8. User memilih clip.
9. User mengedit subtitle, hook, caption, hashtag.
10. User memilih platform.
11. Sistem render di background.
12. User download hasil.

## Admin Flow

1. Admin login.
2. Admin melihat total user/project/render/output.
3. Admin melihat semua project.
4. Admin melihat render status dan error log.
5. Admin mengatur upload limit user.
6. Admin mengatur subtitle template dan export preset.
