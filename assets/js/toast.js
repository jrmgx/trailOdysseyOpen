const showToastWithUndo = (message, undoCallback, duration = 5000) => {
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(container);
  }

  const toastElement = document.createElement('div');
  toastElement.className = 'toast';
  toastElement.setAttribute('role', 'alert');
  toastElement.setAttribute('aria-live', 'assertive');
  toastElement.setAttribute('aria-atomic', 'true');

  const toastBody = document.createElement('div');
  toastBody.className = 'toast-body d-flex justify-content-between align-items-center';

  const messageSpan = document.createElement('span');
  messageSpan.textContent = message;
  toastBody.appendChild(messageSpan);

  const undoButton = document.createElement('button');
  undoButton.className = 'btn btn-sm btn-outline-primary ms-3';
  undoButton.textContent = 'Undo';

  const toast = new bootstrap.Toast(toastElement, {
    delay: duration,
    autohide: true,
  });

  undoButton.onclick = () => {
    undoCallback();
    toast.hide();
  };
  toastBody.appendChild(undoButton);

  toastElement.appendChild(toastBody);
  container.appendChild(toastElement);

  toast.show();

  toastElement.addEventListener('hidden.bs.toast', () => {
    toastElement.remove();
  });
};

export default showToastWithUndo;
