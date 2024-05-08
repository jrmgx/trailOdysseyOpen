import { startStimulusApp } from '@symfony/stimulus-bridge';

// Registers Stimulus controllers from controllers.json and in the controllers/ directory
const app = startStimulusApp(require.context(
  '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
  true,
  /\.[jt]sx?$/,
));

document.addEventListener('turbo:frame-missing', (event) => {
  const { detail: { response, visit } } = event;
  event.preventDefault();
  if (/\/login$/.test(response.url)) {
    // User has been logged off
    visit(document.location);
  } else {
    // eslint-disable-next-line no-alert
    alert('Sorry an error occurred. Try to reload the page.');
    // eslint-disable-next-line no-console
    console.error('An error occurred! Frame missing.', event);
  }
});

export default app;
