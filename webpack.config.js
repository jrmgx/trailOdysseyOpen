const Encore = require('@symfony/webpack-encore');
const FosRouting = require('fos-router/webpack/FosRouting');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
  .addPlugin(new FosRouting())
  // directory where compiled assets will be stored
  .setOutputPath('public/build/')
  // public path used by the web server to access the output path
  .setPublicPath('/build')
  // only needed for CDN's or subdirectory deploy
  //.setManifestKeyPrefix('build/')

  /*
   * ENTRY CONFIG
   */
  .addEntry('app', './assets/app.js')

  // enables the Symfony UX Stimulus bridge (used in assets/bootstrap.js)
  .enableStimulusBridge('./assets/controllers.json')

  // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
  .splitEntryChunks()

  // will require an extra script tag for runtime.js
  // but, you probably want this, unless you're building a single-page app
  .enableSingleRuntimeChunk()

  /*
   * FEATURE CONFIG
   *
   * Enable & configure other features below. For a full
   * list of features, see:
   * https://symfony.com/doc/current/frontend.html#adding-more-features
   */
  .cleanupOutputBeforeBuild()
  .enableBuildNotifications()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())

  // configure Babel
  // .configureBabel((config) => {
  //     config.plugins.push('@babel/a-babel-plugin');
  // })

  // enables and configure @babel/preset-env polyfills
  .configureBabelPresetEnv((config) => {
    config.useBuiltIns = 'usage';
    config.corejs = '3.23';
  })

  .copyFiles({
    from: './assets/images',
    to: 'images/[path][name].[hash:8].[ext]',
    pattern: /\.(png|jpg|jpeg|gif|svg)$/
  })

  // enables Sass/SCSS support
  //.enableSassLoader()

  // uncomment if you use TypeScript
  //.enableTypeScriptLoader()

  // uncomment if you use React
  //.enableReactPreset()

  // uncomment to get integrity="..." attributes on your script & link tags
  // requires WebpackEncoreBundle 1.4 or higher
  //.enableIntegrityHashes(Encore.isProduction())

  // uncomment if you're having problems with a jQuery plugin
  //.autoProvidejQuery()
;

module.exports = Encore.getWebpackConfig();
