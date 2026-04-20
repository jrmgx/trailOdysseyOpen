import { Controller } from '@hotwired/stimulus';
import Routing from 'fos-router';

export default class extends Controller {
  static targets = ['textarea', 'input', 'button'];

  static values = {
    trip: Number,
    csrf: String,
    symbolId: String,
  };

  selectFiles = () => {
    this.inputTarget.click();
  };

  upload = async () => {
    const { files } = this.inputTarget;
    if (!files.length) {
      return;
    }

    this.buttonTarget.disabled = true;
    const originalText = this.buttonTarget.textContent;
    this.buttonTarget.textContent = '...';

    const url = Routing.generate('photo_upload', { trip: this.tripValue });
    const uploads = Array.from(files).map((file) => this.uploadFile(url, file));
    const results = await Promise.allSettled(uploads);

    let hasUploaded = false;
    results.forEach(({ status, value }) => {
      if (status === 'fulfilled' && value) {
        this.insertMarkdown(value);
        hasUploaded = true;
      }
    });

    if (hasUploaded && this.symbolIdValue) {
      const symbolInput = document.getElementById(this.symbolIdValue);
      if (symbolInput) {
        symbolInput.value = '\uD83D\uDDBC\uFE0F';
      }
    }

    this.inputTarget.value = '';
    this.buttonTarget.disabled = false;
    this.buttonTarget.textContent = originalText;
  };

  async uploadFile(url, file) {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch(url, {
      method: 'POST',
      body: formData,
      headers: { 'X-CSRF-Token': this.csrfValue },
    });

    if (!response.ok) {
      return null;
    }

    const { markdown } = await response.json();
    return markdown;
  }

  insertMarkdown(markdown) {
    const textarea = this.textareaTarget;
    const { selectionStart, selectionEnd, value } = textarea;

    const prefix = selectionStart > 0 && value[selectionStart - 1] !== '\n' ? '\n' : '';
    const suffix = '\n';
    const insertion = prefix + markdown + suffix;

    textarea.value = value.substring(0, selectionStart) + insertion + value.substring(selectionEnd);
    textarea.selectionStart = selectionStart + insertion.length;
    textarea.selectionEnd = textarea.selectionStart;

    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.focus();
  }
}
