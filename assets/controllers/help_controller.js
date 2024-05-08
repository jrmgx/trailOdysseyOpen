// noinspection JSUnusedGlobalSymbols

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['tableOfContent'];

  static values = {};

  // connect = () => {
  //   const titleElements = document.querySelectorAll('.title-second[id]');
  //   for (const e of titleElements) {
  //     const a = document.createElement('a');
  //     a.href = `#${e.id}`;
  //     a.innerHTML = e.innerHTML;
  //     this.tableOfContentTarget.appendChild(a);
  //   }
  // };
}
