import { BridgeComponent } from '@hotwired/hotwire-native-bridge';

export default class extends BridgeComponent {
    static component = 'file-picker';

    pick(accept) {
        this.send('pick', { accept: accept ?? 'audio/*' }, (response) => {
            this.dispatch('picked', { detail: response });
        });
    }
}
