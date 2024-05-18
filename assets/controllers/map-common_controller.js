// noinspection JSUnusedGlobalSymbols

import * as Turbo from '@hotwired/turbo';
import L from 'leaflet';
import { Controller } from '@hotwired/stimulus';
import { Point } from 'leaflet/src/geometry';
import { LatLng } from 'leaflet/src/geo';
// https://makinacorpus.github.io/Leaflet.GeometryUtil/index.html
import 'leaflet-geometryutil';
import '@elfalem/leaflet-curve';
import '../js/leaflet-double-touch-drag-zoom';
import { markerDefaultIcon, removeFromMap } from '../helpers';
// import './TileLayer.GeoJSON';

export default class extends Controller {
  static targets = [
    'map',
    'containerProgress',
    'progressText',
  ];

  static values = {
    options: Object,
    urls: Object,
    tiles: Array,
    translations: Object,
    cacheName: String,
    isPublic: Boolean,
    isLive: Boolean,
  };

  connect = () => {
    this.elevationCurrentPoint = null;
    this.actionPinActiveFor = null;
    this.elements = [];
    // Warning: paths are indexed by their start stage id
    this.paths = {};
    // Warning: points are indexed by their start stage id
    this.points = {}; // Raw data for each stage
    this.activeChart = null;
    this.isPublic = !!this.isPublicValue;
    this.isLive = !!this.isLiveValue;

    this.findPathCloseToPointOnce = false;

    // Init map

    const baseLayers = {};
    const overlayLayers = {};
    // const geoJsonLayers = {};
    const firstLayer = [];

    for (const tiles of this.tilesValue) {
      const currentLayer = L.tileLayer(tiles.proxyUrl, {
        maxZoom: 19,
        attribution: tiles.description || '',
      });
      if (tiles.geoJson) {
        // var currentLayer = new L.TileLayer.GeoJSON(tiles.proxyUrl);
        // geoJsonLayers[tiles.name] = currentLayer;
        // baseLayers[tiles.name] = currentLayer;
        // this.map.addLayer(currentLayer);
      } else if (tiles.overlay) {
        overlayLayers[tiles.name] = currentLayer;
      } else {
        if (firstLayer.length === 0) {
          firstLayer.push(currentLayer);
        }
        baseLayers[tiles.name] = currentLayer;
      }
    }

    this.map = L.map('map', {
      layers: firstLayer,
      preferCanvas: true,
      doubleTouchDragZoom: true,
      doubleTouchDragZoomInvert: true,
      zoomControl: !this.isPublic,
    });
    this.map.setView(
      [parseFloat(this.optionsValue.center.lat), parseFloat(this.optionsValue.center.lon)],
      this.optionsValue.zoom,
    );

    this.pathsLayerGroup = L.featureGroup().addTo(this.map);

    if (this.tilesValue.length > 1) {
      L.control.layers(baseLayers, overlayLayers).addTo(this.map);
      const control = document.querySelector('.leaflet-control-layers');
      control.remove();
      document.querySelector('.map-button-container').appendChild(control);
      control.classList.add('with-box-shadow-radius');
      if (!this.isLive) {
        control.classList.add('hide-mobile');
      }
      if (this.isPublic) {
        control.classList.add('hide');
      }
    }

    if (!this.isPublic) {
      this.map.on('click', this.mapClickAction);
      this.map.on('zoomend', this.mapZoomMoveEndHandler);
      this.map.on('moveend', this.mapZoomMoveEndHandler);
    }

    // Export method for external use
    window.mapCommonController = {
      addElement: this.addElement,
      removeAllElements: this.removeAllElements,
      addProgress: this.addProgress,
      // updateProgress: this.updateProgress,
      addPath: this.addPath,
      addPathReference: this.addPathReference,
      addElevation: this.addElevation,
      refreshPlan: this.refreshPlan,
      mapClickActionDelegate: this.mapClickActionDelegate,
      downloadOfflinePoints: this.downloadOfflinePoints,
      getIsOffline: this.getIsOffline,
      removeOffline: this.removeOffline,
      findPathCloseToPoint: this.findPathCloseToPoint,
      map: this.map,
    };
  };

  // Actions

  newPinAction = (e) => {
    const { type, on, off } = e.params || {};
    if (this.actionPinActiveFor) {
      e.target.closest('.btn').innerHTML = on;
      this.stopPinAction();
      return;
    }
    this.actionPinActiveFor = type;
    document.body.style.cursor = 'crosshair';
    this.mapTarget.style.cursor = 'crosshair';
    e.target.closest('.btn').innerHTML = off;
    sidebarController.switchToMapAction();
  };

  stopPinAction = () => {
    this.actionPinActiveFor = null;
    document.body.style.cursor = 'default';
    this.mapTarget.style.cursor = 'grab';
  };

  mapClickActionDelegate = (callback) => {
    this.privateMapClickActionDelegate = callback;
  };

  mapClickAction = (e) => {
    sidebarController.searchContainerHide();

    if (this.privateMapClickActionDelegate) {
      this.privateMapClickActionDelegate(e, this.actionPinActiveFor);
    }

    this.stopPinAction();
  };

  // Marker related

  addElement = (lat, lon, popup, openPopup) => {
    const element = L.marker([parseFloat(lat), parseFloat(lon)], { icon: markerDefaultIcon })
      .bindPopup(popup)
      .addTo(this.map);
    this.elements.push(element);
    if (openPopup) {
      setTimeout(() => element.openPopup(), 300);
    }
    return element;
  };

  removeAllElements = () => {
    for (let marker of this.elements) {
      marker = removeFromMap(marker, this.map);
    }
  };

  addProgress = (...args) => {
    if (this.progress) {
      this.progress.remove();
    }

    const progress = args.shift();
    const paths = args;

    const latLon = [];
    let found = false;
    for (const points of paths) {
      if (found) {
        break;
      }
      for (const p of points) {
        if (p.lat === progress[0] && p.lon === progress[1]) {
          found = true;
          break;
        }
        latLon.push([p.lat, p.lon]);
      }
    }
    if (!found) {
      return;
    }
    this.progress = L.polyline(latLon, { interactive: false, color: 'red', weight: 6 })
      .setStyle({ cursor: 'default' })
      .addTo(this.map);
  };

  // Event based

  updateSearchBoundingBox = () => {
    if (!document.querySelector('#geo_element_southWest_lon')) {
      return;
    }
    const b = this.map.getBounds();
    document.querySelector('#geo_element_southWest_lon').value = b.getSouthWest().lng;
    document.querySelector('#geo_element_southWest_lat').value = b.getSouthWest().lat;
    document.querySelector('#geo_element_northEast_lon').value = b.getNorthEast().lng;
    document.querySelector('#geo_element_northEast_lat').value = b.getNorthEast().lat;
  };

  mapZoomMoveEndHandler = () => {
    this.updateSearchBoundingBox();
    sidebarController.sendMapOptionDebounced(this.map.getZoom(), this.map.getCenter());
  };

  // Paths

  addPath = (points, stageId) => {
    this.points[stageId] = points;
    const latLon = [];
    for (const p of points) {
      latLon.push([p.lat, p.lon]);
    }
    const polyline = L.polyline(latLon, {
      color: 'blue',
      opacity: 0.75,
      weight: 3,
      lineCap: 'round',
      dashArray: '3, 5',
      dashOffset: '0',
    });
    this.paths[stageId] = polyline;
    this.pathsLayerGroup.addLayer(polyline);
  };

  addPathReference = (points, stageId) => {
    this.points[stageId] = points;
  };

  findPathCloseToPoint = (point) => {
    let maxDistance = Number.MAX_VALUE;
    let probablePath = null;
    let probablePoints = null;
    let probableStage = null;
    let closestToPoint = null;
    let index = null;
    for (index in this.paths) {
      const path = this.paths[index];
      const closest = L.GeometryUtil.closest(this.map, path, point);
      const { distance } = closest;
      // eslint-disable-next-line no-continue
      if (distance > maxDistance) continue;
      maxDistance = distance;
      probablePath = path;
      probablePoints = this.points[index];
      closestToPoint = closest;
      probableStage = index;
    }

    if (probablePath === null) {
      // eslint-disable-next-line no-console
      console.error('findPathCloseToPoint error');
      return false;
    }

    const activeStage = liveController.getActiveStage();
    const correctStage = `${activeStage}` === `${probableStage}`;
    if (!correctStage && !this.findPathCloseToPointOnce) {
      // eslint-disable-next-line no-alert
      if (window.confirm('Do you want to update the stage to the current one?')) {
        liveController.setActiveStage(probableStage);
        Turbo.visit(this.urlsValue.liveShowStage.replace('/0', `/${probableStage}`), { frame: 'live-stage', action: 'advance' });
        return false;
      }
    }
    this.findPathCloseToPointOnce = true;

    if (!correctStage) {
      return false;
    }

    const totalDistance = L.GeometryUtil.length(probablePath);
    const percentOfPath = L.GeometryUtil.locateOnLine(this.map, probablePath, closestToPoint);
    // eslint-disable-next-line max-len
    const fromPercentOfPath = L.GeometryUtil.interpolateOnLine(this.map, probablePath, percentOfPath);
    const pointDistance = (totalDistance * percentOfPath) / 1000; // in km
    const predecessor = Math.max(0, fromPercentOfPath.predecessor);
    const elevation = probablePoints[predecessor].el || 0;

    // Debug
    // const debug = false;
    // if (debug) {
    //   let show = closestToPoint;
    //   show = fromPercentOfPath.latLng;
    //   if (this.findPathCloseToPointMarker) {
    //     this.findPathCloseToPointMarker.remove();
    //   }
    //   this.findPathCloseToPointMarker = L.circleMarker(
    //     [show.lat, show.lng],
    //     { color: '#F00', radius: 2 },
    //   ).addTo(this.map);
    // }

    if (this.activeChart) {
      this.activeChart.updateOptions({
        annotations: {
          points: [{
            x: pointDistance,
            y: elevation,
            marker: {
              size: 5,
              fillColor: '#0d6efd',
              strokeColor: '#0d6efd',
              strokeWidth: 0,
            },
            label: {
              borderColor: 'black',
              text: this.formatKm(pointDistance),
            },
          }],
        },
      });
    }

    if (this.hasProgressTextTarget) {
      this.progressTextTarget.innerText = `${Math.round(percentOfPath * 100)}%`;
    }

    return true;
  };

  refreshPlan = () => {
    this.pathsLayerGroup.bringToFront();
  };

  // Offline related

  downloadOfflinePoints = (routingId, latLngs, callback) => {
    const urls = [...new Set(this.gatherTileUrls(latLngs))];
    const CACHE_NAME = this.cacheNameValue;
    const total = urls.length;
    // eslint-disable-next-line no-alert
    if (!window.confirm(`This will download ${total} tiles`)) return;
    const containerProgress = this.containerProgressTarget;
    containerProgress.classList.remove('hide');
    const progressBar = this.containerProgressTarget.querySelector('.progress-bar');
    progressBar.style.width = '0';
    progressBar.innerText = '0%';
    (async () => {
      const cache = await caches.open(CACHE_NAME);
      let i = 0;
      for (const u of urls) {
        i += 1;
        const current = Math.ceil((i / total) * 100);
        progressBar.style.width = `${current}%`;
        progressBar.innerText = `${current}%`;
        // eslint-disable-next-line no-await-in-loop
        if (await cache.match(u, { ignoreVary: true })) {
          // eslint-disable-next-line no-continue
          continue;
        }
        // eslint-disable-next-line no-await-in-loop
        const responseToCache = await fetch(u);
        // eslint-disable-next-line no-await-in-loop
        await cache.put(u, responseToCache);
        // eslint-disable-next-line no-await-in-loop
        await new Promise((r) => { setTimeout(r, 50); });
      }
      this.setIsOffline(routingId);
      callback();
      containerProgress.classList.add('hide');
    })();

    // TODO persist action see https://web.dev/articles/offline-cookbook?hl=en#cache-persistence
  };

  setIsOffline = (routingId) => {
    localStorage.setItem(`${this.cacheNameValue}_offline_tiles_${routingId}`, 'true');
  };

  getIsOffline = (routingId) => localStorage.getItem(`${this.cacheNameValue}_offline_tiles_${routingId}`) !== null;

  // This does not delete the cache, only the flag
  removeOffline = (routingId) => localStorage.removeItem(`${this.cacheNameValue}_offline_tiles_${routingId}`);

  // This one will delete the cache
  clearOffline = () => {
    // TODO
  };

  gatherTileUrls = (points) => {
    const tileSize = 256; // Leaflet default tile size
    let activeTileLayer = null;
    this.map.eachLayer((layer) => {
      if (layer instanceof L.TileLayer) {
        activeTileLayer = layer;
      }
    });
    const urls = [];
    const minZoom = Math.max(this.map.getMinZoom(), 12);
    const maxZoom = this.map.getMaxZoom() - 1;
    const currentZoom = this.map.getZoom();
    const adjacent = [
      [-1, +1], [+0, +1], [+1, +1],
      [-1, +0], [+0, +0], [+1, +0],
      [-1, -1], [+0, -1], [+1, -1],
    ];
    for (const pt of points) {
      for (let zoom = minZoom; zoom <= maxZoom; zoom += 1) {
        const projection = this.map.project(pt, zoom).divideBy(tileSize).floor();
        for (const a of adjacent) {
          const pr = projection.add(new Point(a[0], a[1]));
          let tileUrl = activeTileLayer.getTileUrl(pr);
          tileUrl = tileUrl.replace(new RegExp(`/${currentZoom}$`), `/${zoom}`);
          urls.push(tileUrl);
        }
      }
    }
    return urls;
  };

  // Elevation visualization

  formatKm = (m) => `${Math.round(m * 100) / 100} km`;

  formatMeters = (m) => `${Math.round(m)} m`;

  formatVoid = () => '';

  elevationMouseMove = (event, chartContext, chartOptions) => {
    if (this.elevationCurrentPoint) {
      this.elevationCurrentPoint.remove();
    }
    const { dataPointIndex } = chartOptions;
    const { config } = chartOptions;
    if (config.series[0].data[dataPointIndex]) {
      const point = config.series[0].data[dataPointIndex];
      this.elevationCurrentPoint = L.circleMarker(
        [point.lat, point.lon],
        {
          color: 'red', radius: 8, fill: true, fillColor: 'blue',
        },
      ).addTo(this.map);
    }
  };

  elevationMouseClick = (event, chartContext, chartOptions) => {
    const { dataPointIndex } = chartOptions;
    const { config } = chartOptions;
    if (config.series[0].data[dataPointIndex]) {
      const point = config.series[0].data[dataPointIndex];
      this.map.panTo({ lat: point.lat, lng: point.lon });
    }
  };

  elevationMouseLeave = () => {
    this.elevationCurrentPoint = removeFromMap(this.elevationCurrentPoint, this.map);
  };

  addElevation = (stageId, minimal) => {
    const element = document.querySelector(`#stage-elevation-${stageId}`);
    if (!element) return null;
    const points = this.points[stageId];
    if (!points) {
      // element.parentNode.remove();
      return null;
    }
    const computedStyle = getComputedStyle(document.querySelector('.live-graph'));
    const chart = new ApexCharts(element, {
      series: [{ data: this.elevationPrepareData(stageId) }],
      colors: ['#0d6efd'],
      xaxis: {
        type: 'numeric',
        labels: { show: false },
        axisTicks: { show: false },
        axisBorder: { show: false },
        tooltip: { formatter: this.formatKm },
      },
      yaxis: {
        labels: { formatter: this.formatMeters },
      },
      chart: {
        parentHeightOffset: 0,
        toolbar: { show: false },
        height: computedStyle.getPropertyValue('height'),
        type: 'line',
        zoom: { enabled: false },
        animations: { enabled: false },
        events: {
          mouseMove: this.elevationMouseMove,
          mouseLeave: this.elevationMouseLeave,
          click: this.elevationMouseClick,
        },
      },
      stroke: {
        curve: 'straight',
        width: 3,
      },
      tooltip: {
        enabled: true,
        x: { show: false },
        y: {
          formatter: this.formatMeters,
          title: { formatter: this.formatVoid },
        },
        fixed: {
          enabled: true,
          position: 'topLeft',
          offsetX: 0,
          offsetY: 0,
        },
      },
    });
    chart.render();
    if (minimal) {
      chart.updateOptions({
        chart: {
          sparkline: { enabled: true },
          type: 'area',
        },
        fill: { opacity: 0.3 },
        grid: { show: false },
        yaxis: {
          labels: { show: false },
          axisTicks: { show: false },
          axisBorder: { show: false },
        },
        stroke: { width: 2 },
        tooltip: {
          fixed: { offsetX: 10 },
        },
      });
    }
    this.activeChart = chart;
    return chart;
  };

  elevationPrepareData = (stageId) => {
    const points = this.points[stageId];
    if (!points) return [];
    const pointsLatLng = points.map((p) => new LatLng(p.lat, p.lon, p.el));
    const accumulatedLengths = L.GeometryUtil.accumulatedLengths(pointsLatLng);
    const max = accumulatedLengths.length;
    const result = [];
    for (let i = 0; i < max; i += 1) {
      const p = pointsLatLng[i];
      result.push({
        x: accumulatedLengths[i] / 1000, y: p.alt, lat: p.lat, lon: p.lng,
      });
    }
    return result;
  };

  // Helpers

  preventWarnings = () => {
    // Targets
    this.mapTarget = null;
    this.progressTextTarget = null;
    this.hasProgressTextTarget = null;
    // Values
    this.cacheNameValue = null;
    this.isPublicValue = null;
    this.isLiveValue = null;
    this.optionsValue = { center: { lat: null, lon: null } };
    this.urlsValue = {
      mapSearch: null,
      stageNew: null,
      interestNew: null,
      photoNew: null,
      stageMove: null,
      interestMove: null,
      diaryEntryNew: null,
      mapOption: null,
      liveShowStage: null,
    };
    this.tilesValue = null;
    this.translationsValue = {
      clickMapToAdd: null,
      orHereToCancel: null,
    };
  };
}
