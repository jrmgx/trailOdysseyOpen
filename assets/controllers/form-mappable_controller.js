// noinspection JSUnusedGlobalSymbols

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['arrivingAt', 'leavingAt'];

  static values = {};

  connect = () => {
    if (!this.hasLeavingAtTarget) {
      return;
    }

    let arrivingAtDate;
    let leavingAtDate;
    let timezoneOffset;

    const init = () => {
      arrivingAtDate = new Date(this.arrivingAtTarget.value);
      leavingAtDate = new Date(this.leavingAtTarget.value);
      timezoneOffset = leavingAtDate.getTimezoneOffset() * 60 * 1000;
    };

    init();

    this.leavingAtTarget.addEventListener('change', init);

    this.arrivingAtTarget.addEventListener('change', () => {
      const d = arrivingAtDate - new Date(this.arrivingAtTarget.value) + timezoneOffset;
      this.leavingAtTarget.value = new Date(leavingAtDate - d).toISOString().substring(0, 16);
    });
  };
}
