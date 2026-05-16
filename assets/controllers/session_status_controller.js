import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { dashboard: Boolean, url: String };

    connect() {
        if (this.hasUrlValue) {
            this.source = new EventSource(this.urlValue);
            this.source.onmessage = () => {
                if (this.dashboardValue) {
                    document.body.dispatchEvent(new CustomEvent('flowvox:refresh-dashboard'));
                }
            };
        }
    }

    disconnect() {
        this.source?.close();
    }
}
