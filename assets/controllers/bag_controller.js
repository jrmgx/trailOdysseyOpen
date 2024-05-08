import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';

export default class extends Controller {
  checkedAction = (e) => {
    let { url } = e.params;
    url += `?checked=${e.target.checked ? 1 : 0}`;
    Turbo.visit(url, { frame: 'main' });
  };
}
