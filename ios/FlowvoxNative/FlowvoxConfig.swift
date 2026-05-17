import Foundation

/// Backend base URL for the Symfony web app.
///
/// Override in Info.plist (`FLOWVOX_BASE_URL`) without recompiling.
/// - Simulator: `http://127.0.0.1:8000` (Symfony / PHP on the same Mac)
/// - Physical device: `http://<Mac-LAN-IP>:8000` (e.g. `http://192.168.1.42:8000`)
enum FlowvoxConfig {
    static var baseURL: URL {
        if let string = Bundle.main.object(forInfoDictionaryKey: "FLOWVOX_BASE_URL") as? String,
           !string.isEmpty,
           let url = URL(string: string)
        {
            return url
        }

        #if targetEnvironment(simulator)
        return URL(string: "http://127.0.0.1:8000")!
        #else
        fatalError(
            "Set FLOWVOX_BASE_URL in Info.plist to your Mac's LAN address, e.g. http://192.168.1.42:8000"
        )
        #endif
    }
}
