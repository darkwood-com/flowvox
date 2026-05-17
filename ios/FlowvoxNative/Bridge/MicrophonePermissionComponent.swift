import AVFoundation
import HotwireNative
import UIKit

/// Matches Stimulus `microphone-permission` on the Settings page.
final class MicrophonePermissionComponent: BridgeComponent {
    override nonisolated class var name: String { "microphone-permission" }

    override func onReceive(message: Message) {
        switch message.event {
        case "connect", "request":
            requestMicrophoneAccess()
        default:
            break
        }
    }

    private func requestMicrophoneAccess() {
        AVAudioSession.sharedInstance().requestRecordPermission { [weak self] granted in
            DispatchQueue.main.async {
                self?.reply(
                    to: "request",
                    with: ResponseData(granted: granted)
                )
            }
        }
    }
}

private extension MicrophonePermissionComponent {
    struct ResponseData: Encodable {
        let granted: Bool
    }
}
