import HotwireNative
import UIKit

@main
class AppDelegate: UIResponder, UIApplicationDelegate {
    func application(
        _ application: UIApplication,
        didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
    ) -> Bool {
        let localConfig = Bundle.main.url(forResource: "path-configuration", withExtension: "json")!
        let remoteConfig = FlowvoxConfig.baseURL.appending(path: "/config/ios_v1.json")

        Hotwire.loadPathConfiguration(from: [
            .file(localConfig),
            .server(remoteConfig),
        ])

        Hotwire.registerBridgeComponents([
            SettingsBarButtonComponent.self,
            MicrophonePermissionComponent.self,
            NativeRecorderComponent.self,
        ])

        return true
    }
}
