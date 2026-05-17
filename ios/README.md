# Flowvox — Hotwire Native (iOS)

Wrap the Symfony web app in a native shell. The server must be running (`symfony server:start` or Docker).

## Prerequisites

- Xcode 15+
- Flowvox backend reachable from the simulator/device (see [Base URL](#base-url))
- `php bin/console ux-native:dump` after changing native configuration (production)

## Quick start

1. **Xcode** → File → New → Project → iOS **App**
   - Product name: `FlowvoxNative`
   - Language: Swift
   - Interface: Storyboard (default)

2. **Add package** → File → Add Package Dependencies…
   - URL: `https://github.com/hotwired/hotwire-native-ios`
   - Add to target `FlowvoxNative`

3. **Copy Swift sources** from this folder into the Xcode project:
   - `FlowvoxNative/AppDelegate.swift` → replace generated `AppDelegate.swift`
   - `FlowvoxNative/SceneDelegate.swift` → replace generated `SceneDelegate.swift`
   - `FlowvoxNative/Bridge/*.swift` → add to target (Create groups)

4. **Add** `path-configuration.json` to the app target (Copy Bundle Resources).

5. **Info.plist** — allow local Symfony dev server (HTTP + self-signed HTTPS):

```xml
<key>NSAppTransportSecurity</key>
<dict>
    <key>NSAllowsLocalNetworking</key>
    <true/>
    <key>NSExceptionDomains</key>
    <dict>
        <key>127.0.0.1</key>
        <dict>
            <key>NSExceptionAllowsInsecureHTTPLoads</key>
            <true/>
            <key>NSIncludesSubdomains</key>
            <true/>
        </dict>
        <key>localhost</key>
        <dict>
            <key>NSExceptionAllowsInsecureHTTPLoads</key>
            <true/>
            <key>NSIncludesSubdomains</key>
            <true/>
        </dict>
    </dict>
</dict>
```

6. **Microphone** (voice sessions):

```xml
<key>NSMicrophoneUsageDescription</key>
<string>Flowvox records audio for transcription.</string>
```

7. Set **Scene configuration** in Info.plist if needed (UIKit scene manifest — default for new projects is fine).

8. Run on simulator. You should see the Flowvox dashboard with a native **Settings** bar button.

## Base URL

Edit `rootURL` in `SceneDelegate.swift` and `AppDelegate.swift`:

| Environment | URL |
|-------------|-----|
| Simulator + Symfony CLI | `https://127.0.0.1:8000` |
| Physical device | Mac LAN IP, e.g. `https://192.168.1.42:8000` |

Path configuration is loaded from:

- bundled `path-configuration.json`
- remote `https://<host>/config/ios_v1.json` (served by Symfony UX Native in `dev`)

## Bridge components

| Web (Stimulus) | iOS (Swift) | Purpose |
|----------------|-------------|---------|
| `settings-bar-button` | `SettingsBarButtonComponent` | Nav bar → Settings |
| `microphone-permission` | `MicrophonePermissionComponent` | Request mic access |
| `native-recorder` | `NativeRecorderComponent` | Hook for native record UI (stub) |

Register all components in `AppDelegate.swift` (`Hotwire.registerBridgeComponents`).

## Verify native detection

The iOS WebView sends a `User-Agent` containing `Hotwire Native`. Twig can branch with `ux_is_native()` (navbar hidden, bridge links active).

## Production

```bash
php bin/console ux-native:dump
```

Deploys `public/config/ios_v1.json`. Point the app at your production host.
