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

Database: SQLite by default (`var/data.db`). Set `DATABASE_URL` in `.env` to use another driver.

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

## Web UI

Start Mercure (Docker):

```bash
docker compose up -d mercure
```

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
| `openai_batch` | OpenAI Whisper via Symfony AI |
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
php bin/console voice:record-test
php bin/console voice:stream-test --seconds=15
php bin/console voice:transcribe-test /path/to/file.wav
php bin/console voice:watch-folder --dir=var/watch
```

## Native app (Hotwire Native)

Flowvox includes `symfony/ux-native` for iOS/Android shells. Web-first Twig templates work in WKWebView; bridge controllers: `microphone-permission`, `native-recorder`, `file-picker`, `share-export`.

Generate native config:

```bash
php bin/console ux:native:generate-config
```

## Architecture

- **Workers** (`voice:worker`) — Flow pipeline: Messenger control → record (ffmpeg batch or whisper-stream) → transcribe
- **UI** — Twig + UX Turbo + Mercure (observe + control only)
- **Application layer** — `SendVoiceControl`, `RecordWorkerEvent`, transcription providers
