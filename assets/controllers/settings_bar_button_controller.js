import { BridgeComponent } from '@hotwired/hotwire-native-bridge';

/**
 * Native navigation bar button → navigates to the linked page when tapped.
 * Swift: SettingsBarButtonComponent (ios/FlowvoxNative/Bridge/).
 */
export default class extends BridgeComponent {
    static component = 'settings-bar-button';

    connect() {
        super.connect();

        const title = this.bridgeElement.title || 'Settings';
        this.send('connect', { title }, () => {
            this.element.click();
        });
    }
}
