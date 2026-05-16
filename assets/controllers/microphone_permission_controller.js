import { Controller } from '@hotwired/stimulus';
import { BridgeComponent } from '@hotwired/hotwire-native-bridge';

export default class extends BridgeComponent {
    static component = 'microphone-permission';

    connect() {
        super.connect();
        this.send('connect', {}, () => {});
    }

    request() {
        this.send('request', {}, (response) => {
            this.dispatch('granted', { detail: response });
        });
    }
}
