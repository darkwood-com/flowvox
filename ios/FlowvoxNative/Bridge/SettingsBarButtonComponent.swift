import HotwireNative
import UIKit

/// Matches Stimulus `settings-bar-button` — nav bar button for Settings.
final class SettingsBarButtonComponent: BridgeComponent {
    override nonisolated class var name: String { "settings-bar-button" }

    override func onReceive(message: Message) {
        guard message.event == "connect", let viewController else { return }
        addButton(via: message, to: viewController)
    }

    private var viewController: UIViewController? {
        delegate?.destination as? UIViewController
    }

    private func addButton(via message: Message, to viewController: UIViewController) {
        guard let data: MessageData = message.data() else { return }

        let action = UIAction { [unowned self] _ in
            self.reply(to: "connect")
        }
        let item = UIBarButtonItem(title: data.title, primaryAction: action)
        viewController.navigationItem.rightBarButtonItem = item
    }
}

private extension SettingsBarButtonComponent {
    struct MessageData: Decodable {
        let title: String
    }
}
