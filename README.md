<p align="center">
  <a href="https://github.com/darkwood-com/flowvox">
    <img src="public/logo.png" width="auto" height="128px" alt="Flow">
  </a>
</p>

# Flowvox

Symfony 8 voice worker with Flow pipeline, Messenger control, web dashboard (Turbo + Mercure), and pluggable transcription (whisper.cpp local, OpenAI batch/realtime).

## Setup

```bash
composer install
php bin/console doctrine:migrations:migrate --no-interaction
```

Database: SQLite by default (`var/data.db`). If you run `docker compose up` with the Postgres service, the Symfony CLI may auto-set `DATABASE_URL` to Postgres — run migrations on that database:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

To stay on SQLite instead, add to `.env.local`:

```env
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

Configure whisper.cpp in `.env.local`:

```
WHISPER_CLI_PATH=/path/to/whisper-cli
WHISPER_MODEL_PATH=/path/to/ggml-base.bin
```

### Local realtime (whisper-stream)

Build `whisper-stream` with SDL2 (microphone capture is done by whisper-stream, not ffmpeg):

```bash
brew install sdl2
cmake -B build -DWHISPER_SDL2=ON && cmake --build build --config Release
# binary: build/bin/whisper-stream
```

Enable stream mode:

```
FLOWVOX_WHISPER_MODE=stream
WHISPER_STREAM_PATH=/path/to/build/bin/whisper-stream
WHISPER_STREAM_LANGUAGE=fr
```

Test microphone + parser without the worker:

```bash
php bin/console voice:stream-test --seconds=15
```

Only one process should use the microphone on macOS (do not run ffmpeg `voice:record-test` and stream mode at the same time). Recommended models: `base` or `small` for lower latency.

Select the microphone (device indices are **not** the same for stream vs batch):

```bash
php bin/console voice:list-capture-devices
```

```env
# whisper-stream (SDL2), -1 = system default
WHISPER_STREAM_CAPTURE_ID=0
# ffmpeg avfoundation for batch mode
WHISPER_FFMPEG_CAPTURE_DEVICE=0
```

`whisper-cli` only transcribes WAV files; it does not capture audio.

## Web UI

Start Mercure (Docker):

```bash
docker compose up -d mercure
php bin/console voice:mercure-test --session=demo
```

Mercure must answer in **HTTP** on port 3000 (`MERCURE_URL=http://localhost:3000/.well-known/mercure`). If the worker logs `Mercure publish failed` with HTTP 308, recreate the hub after pulling (`docker compose up -d mercure --force-recreate`) — the dev HTTPS Caddy profile redirects to `https://localhost` and breaks publishing.

Start the Symfony web server:

```bash
symfony server:start
```

Open `https://127.0.0.1:8000` — dashboard lists active sessions, START/STOP controls, live transcription via Mercure, transcription history, search, and export (txt/md/srt/vtt).

Run a worker in another terminal:

```bash
php bin/console voice:worker --session=demo
```

Use the web UI or CLI to send START/STOP to session `demo`.

### Transcription providers

Set in `.env.local`:

```
FLOWVOX_TRANSCRIPTION_PROVIDER=whisper_cpp
OPENAI_API_KEY=sk-...
```

| Value | Description |
|-------|-------------|
| `whisper_cpp` | Local whisper.cpp batch after STOP (default, private) |
| `whisper_cpp_stream` | Local whisper-stream (use with `FLOWVOX_WHISPER_MODE=stream`) |
| `openai_batch` | OpenAI `/v1/audio/transcriptions` (default model: `gpt-4o-transcribe`, see `OPENAI_TRANSCRIPTION_MODEL`) |
| `openai_realtime_whisper` | OpenAI Realtime API (streaming partials) |

## Voice worker MVP – multi-terminal test

Use **3 terminals** to verify session listing, targeted START/STOP, and broadcast.

**Terminal A – worker alpha**

```bash
php bin/console voice:worker --session=alpha
```

**Terminal C – worker beta**

```bash
php bin/console voice:worker --session=beta
```

**Terminal B – control**

```bash
php bin/console voice:worker-list
php bin/console voice:start --session=alpha
php bin/console voice:stop --session=beta
php bin/console voice:start
```

Optional: remove stale sessions (heartbeat older than 30s):

```bash
php bin/console voice:worker-list --clean-stale
```

## Other commands

```bash
php bin/console voice:list-capture-devices
php bin/console voice:record-test
php bin/console voice:stream-test --seconds=15
php bin/console voice:transcribe-test /path/to/file.wav
php bin/console voice:watch-folder --dir=var/watch
```

## Native app (Hotwire Native)

Flowvox uses [`symfony/ux-native`](https://symfony.com/bundles/ux-native) to wrap the web UI in iOS/Android shells. One Twig codebase; `ux_is_native()` hides the web navbar in the native app.

### Symfony setup

Already installed: `symfony/ux-native`. Native JSON config is defined in `src/Native/FlowvoxNativeConfiguration.php`:

- iOS: `/config/ios_v1.json`
- Android: `/config/android_v1.json`

In **dev**, these URLs are served dynamically. For **production**, dump static files:

```bash
php bin/console ux-native:dump
# → public/config/ios_v1.json
```

### Bridge components (web)

| Stimulus controller | Role |
|---------------------|------|
| `settings-bar-button` | Nav bar → Settings (iOS example) |
| `microphone-permission` | Request mic on Settings (native) |
| `native-recorder` | Extension point for native record UI |
| `file-picker`, `share-export` | Export / file hooks |

Templates: `{% if not ux_is_native() %}` in `templates/base.html.twig`.

### iOS example app

See **[ios/README.md](ios/README.md)** for Xcode setup (Hotwire Native package, `SceneDelegate`, bridge Swift classes).

1. Start Symfony: `symfony server:start`
2. Create Xcode project, add package `https://github.com/hotwired/hotwire-native-ios`
3. Copy `ios/FlowvoxNative/*.swift` and `path-configuration.json` into the target
4. Set `flowvoxRootURL` to `https://127.0.0.1:8000` (simulator) or your Mac’s LAN IP (device)
5. Run — dashboard loads in WKWebView with a native **Settings** button

Mercure live transcription: from a physical device, use a reachable host (not `127.0.0.1`) for both the web app and `MERCURE_PUBLIC_URL`.

## Architecture

- **Workers** (`voice:worker`) — Flow pipeline: Messenger control → record (ffmpeg batch or whisper-stream) → transcribe
- **UI** — Twig + UX Turbo + Mercure (observe + control only)
- **Application layer** — `SendVoiceControl`, `RecordWorkerEvent`, transcription providers
