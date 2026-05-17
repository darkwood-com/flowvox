import HotwireNative
import UIKit

/// Matches Stimulus `native-recorder` — extension point for native START/STOP UI.
/// Current Flowvox worker uses web forms; this component acknowledges bridge messages.
final class NativeRecorderComponent: BridgeComponent {
    override nonisolated class var name: String { "native-recorder" }

    override func onReceive(message: Message) {
        switch message.event {
        case "start":
            reply(to: "start", with: AckData(status: "ack"))
        case "stop":
            reply(to: "stop", with: AckData(status: "ack"))
        default:
            break
        }
    }
}

private extension NativeRecorderComponent {
    struct AckData: Encodable {
        let status: String
    }
}
