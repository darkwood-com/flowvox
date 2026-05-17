import { Controller } from '@hotwired/stimulus';
import { renderStreamMessage } from '@hotwired/turbo';

export default class extends Controller {
    static targets = ['content'];
    static values = { url: String };

    connect() {
        this.completedBlocks = [];
        this.currentPreview = '';
        this.liveLine = null;

        if (!this.hasUrlValue) {
            return;
        }

        // Send mercureAuthorization cookie (set by Twig mercure() with subscribe option)
        this.source = new EventSource(this.urlValue, { withCredentials: true });
        this.source.onmessage = (event) => {
            const raw = event.data;
            if (typeof raw === 'string' && raw.includes('<turbo-stream')) {
                renderStreamMessage(raw);
                return;
            }

            try {
                const data = JSON.parse(raw);
                if (data.type) {
                    this.appendJsonEvent(data);
                }
            } catch {
                // ignore non-JSON payloads
            }
        };

        this.source.onerror = () => {
            console.warn('Mercure EventSource error — check hub URL and mercureAuthorization cookie');
        };
    }

    disconnect() {
        this.source?.close();
    }

    appendJsonEvent(data) {
        if (data.type === 'heartbeat') {
            return;
        }

        if (data.type === 'transcription_partial' && data.payload?.text) {
            this.appendPartialBlock(data.payload.text, data.occurredAt, false, true);
            return;
        }

        if (data.type === 'transcription_final' && data.payload?.text) {
            this.appendPartialBlock(data.payload.text, data.occurredAt, true);
            return;
        }

        const p = document.createElement('p');
        p.className = `event event-${data.type}`;
        const time = document.createElement('time');
        time.textContent = new Date(data.occurredAt).toLocaleTimeString();
        p.appendChild(time);
        const strong = document.createElement('strong');
        strong.textContent = data.type;
        p.appendChild(strong);
        if (data.payload?.text) {
            p.appendChild(document.createTextNode(' ' + data.payload.text));
        }
        this.contentTarget.appendChild(p);
        this.contentTarget.querySelector('.muted')?.remove();
    }

    appendPartialBlock(text, occurredAt, isFinal = false, isStreaming = false) {
        const trimmed = String(text).trim();
        if (!trimmed) {
            return;
        }

        if (isFinal) {
            this.completedBlocks = [trimmed];
            this.currentPreview = '';
        } else if (isStreaming) {
            this.currentPreview = trimmed;
        } else {
            this.completedBlocks.push(trimmed);
            this.currentPreview = '';
        }

        const parts = [...this.completedBlocks];
        if (this.currentPreview !== '') {
            parts.push(this.currentPreview);
        }
        const cumulative = parts.join(' ');

        if (!this.liveLine) {
            this.liveLine = document.createElement('div');
            this.liveLine.className = 'live-partial';
            this.contentTarget.appendChild(this.liveLine);
            this.contentTarget.querySelector('.muted')?.remove();
        }

        const time = new Date(occurredAt).toLocaleTimeString();
        const latest = this.currentPreview !== '' ? this.currentPreview : trimmed;
        this.liveLine.innerHTML = `
            <p class="live-segment"><time>${time}</time> <span class="segment-latest">${this.escapeHtml(latest)}</span></p>
            <p class="live-cumulative muted">${this.escapeHtml(cumulative)}</p>
        `;

        if (isFinal) {
            this.completedBlocks = [];
            this.currentPreview = '';
            this.liveLine = null;
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
