// noinspection JSUnusedGlobalSymbols

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['collectionContainer'];

  static values = {
    index: Number,
    prototype: String,
  };

  connect = () => {
    this.collectionContainerTarget
      .querySelectorAll('.tiles-form-entry')
      .forEach((item) => {
        this.addMoveLink(item);
        this.addDeleteLink(item);
      });

    this.updatePositions();
  };

  addCollectionElementAction = () => {
    const item = document.createElement('div');
    item.classList.add('tiles-form-entry');
    item.innerHTML = this.prototypeValue.replace(/__name__/g, this.indexValue);
    this.collectionContainerTarget.appendChild(item);
    this.indexValue += 1;
    this.addMoveLink(item);
    this.addDeleteLink(item);
    this.updatePositions();
  };

  addDeleteLink = (item) => {
    const removeButton = document.createElement('button');
    removeButton.innerHTML = 'delete';
    removeButton.classList.add('btn', 'btn-outline-danger', 'btn-sm');

    const buttonsContainer = item.querySelector('.buttons-container-delete');
    buttonsContainer.append(removeButton);

    removeButton.addEventListener('click', (e) => {
      e.preventDefault();
      item.remove();
      this.updatePositions();
    });
  };

  addMoveLink = (item) => {
    const upButton = document.createElement('button');
    upButton.innerHTML = '⬆️';
    upButton.classList.add('btn', 'btn-outline-secondary', 'btn-sm');
    upButton.style.border = 'none';

    const downButton = document.createElement('button');
    downButton.innerHTML = '⬇️';
    downButton.classList.add('btn', 'btn-outline-secondary', 'btn-sm');
    downButton.style.border = 'none';

    const buttonsContainer = item.querySelector('.buttons-container');
    buttonsContainer.append(upButton);
    buttonsContainer.append(downButton);

    upButton.addEventListener('click', (e) => {
      e.preventDefault();
      if (item.previousElementSibling) {
        item.previousElementSibling.before(item);
        this.updatePositions();
      }
    });

    downButton.addEventListener('click', (e) => {
      e.preventDefault();
      if (item.nextElementSibling) {
        item.nextElementSibling.after(item);
        this.updatePositions();
      }
    });
  };

  updatePositions = () => {
    const positionInputs = this.collectionContainerTarget.querySelectorAll('form [id$=_position]');
    let position = 1;
    for (const positionInput of positionInputs) {
      positionInput.value = position;
      position += 1;
    }
  };
}
