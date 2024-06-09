// noinspection JSUnusedGlobalSymbols

import L from 'leaflet';
import { Controller } from '@hotwired/stimulus';
import { Point } from 'leaflet/src/geometry';
import { LatLng } from 'leaflet/src/geo';
// https://makinacorpus.github.io/Leaflet.GeometryUtil/index.html
import 'leaflet-geometryutil';
import '@elfalem/leaflet-curve';
import '../js/leaflet-double-touch-drag-zoom';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';
import { markerDefaultIcon, removeFromMap } from '../helpers';
// import './TileLayer.GeoJSON';

export default class extends Controller {
  static targets = [
    'map',
    'containerProgress',
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
    // Warning: paths/pathDistances are indexed by their start stage id
    this.paths = {};
    this.pathDistances = {}; // Distance in meters
    // Warning: points are indexed by their start stage id
    this.points = {}; // Raw data for each stage
    this.activeChart = null;
    // % based current x position of the current point for that graph
    this.activeChartPosition = null;
    this.isPublic = !!this.isPublicValue;
    this.isLive = !!this.isLiveValue;
    this.hasPreciseMouse = matchMedia('(pointer:fine)').matches;

    // Init map

    this.proxyLayers = {};
    const baseLayers = {};
    const overlayLayers = {};
    // const geoJsonLayers = {};
    const firstLayer = [];

    for (const tiles of this.tilesValue) {
      const tilesUrl = this.isLive || tiles.useProxy ? tiles.proxyUrl : tiles.url;
      const currentLayer = L.tileLayer(tilesUrl, {
        attribution: tiles.description || '',
      });
      currentLayer.id = tiles.id;
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
        if (!this.isLive) {
          this.proxyLayers[tiles.id] = L.tileLayer(tiles.proxyUrl);
        }
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
      addPath: this.addPath,
      addPathReference: this.addPathReference,
      addElevation: this.addElevation,
      updateElevationGraph: this.updateElevationGraph,
      refreshPlan: this.refreshPlan,
      downloadOfflinePoints: this.downloadOfflinePoints,
      getIsOffline: this.getIsOffline,
      removeOffline: this.removeOffline,
      findPathCloseToPoint: this.findPathCloseToPoint,
      map: this.map,
      fit: this.fit,
      mapClickActionDelegate: this.mapClickActionDelegate,
    };
  };

  // Actions

  newPinAction = (e) => {
    const { type, on, off } = e.params || {};
    const thatButton = e.target.closest('.btn');
    const otherButtons = Array.from(thatButton.parentElement.children).filter(
      (btn) => btn !== thatButton,
    );
    if (this.actionPinActiveFor) {
      thatButton.innerHTML = on;
      otherButtons.forEach((btn) => btn.classList.remove('hide'));
      this.stopPinAction();
      return;
    }
    this.actionPinActiveFor = type;
    document.body.style.cursor = 'crosshair';
    this.mapTarget.style.cursor = 'crosshair';
    thatButton.innerHTML = off;
    otherButtons.forEach((btn) => btn.classList.add('hide'));
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
    this.removeAllElements();

    if (this.privateMapClickActionDelegate) {
      this.privateMapClickActionDelegate(e, this.actionPinActiveFor);
    }

    this.stopPinAction();
  };

  // Map related

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

  fit = (arrayOfPoints) => {
    const polyline = L.polyline(arrayOfPoints);
    this.map.fitBounds(polyline.getBounds());
  }

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

  addPath = (points, stageId, distance) => {
    this.points[stageId] = points;
    this.pathDistances[stageId] = distance;
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

  addPathReference = (points, stageId, distance) => {
    this.points[stageId] = points;
    this.pathDistances[stageId] = distance;
  };

  findPathCloseToPoint = (point) => {
    let maxDistance = Number.MAX_VALUE;
    let foundPath = null;
    let foundDistance = null;
    let foundStageIndex = null;
    let closestToPoint = null;
    let index = null;
    for (index in this.paths) {
      const path = this.paths[index];
      const closest = L.GeometryUtil.closest(this.map, path, point);
      const { distance } = closest;
      // eslint-disable-next-line no-continue
      if (distance > maxDistance) continue;
      maxDistance = distance;
      foundPath = path;
      foundDistance = this.pathDistances[index];
      closestToPoint = closest;
      foundStageIndex = index;
    }

    return [foundStageIndex, foundPath, foundDistance, closestToPoint];
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
    let proxyTileLayer = null;
    this.map.eachLayer((layer) => {
      if (layer instanceof L.TileLayer) {
        proxyTileLayer = this.proxyLayers[layer.id];
      }
    });
    if (proxyTileLayer === null) {
      return [];
    }
    const urls = [];
    const minZoom = Math.max(this.map.getMinZoom(), 12);
    const maxZoom = this.map.getMaxZoom() - 1;
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
          let tileUrl = proxyTileLayer.getTileUrl(pr);
          // Zoom is not defined because that tileLayer is not on screen so we fake it
          tileUrl = tileUrl.replace('/NaN', `/${zoom}`);
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

  elevationMouseCommon = (lat, lng) => {
    if (this.elevationCurrentPoint) {
      this.elevationCurrentPoint.setLatLng(L.latLng(lat, lng));
    } else {
      this.elevationCurrentPoint = L.circleMarker(
        [lat, lng],
        {
          color: 'red', radius: 8, fill: true, fillColor: 'blue', fillOpacity: 0.5,
        },
      ).addTo(this.map);
    }
    if (this.elevationRemovePointHandle) {
      clearTimeout(this.elevationRemovePointHandle);
      this.elevationRemovePointHandle = null;
    }
    // On mobile device the elevationMouseLeave is not always triggered so the red marker would stay
    this.elevationRemovePointHandle = setTimeout(this.elevationMouseLeave, 10 * 1000);
    return L.latLng(lat, lng);
  };

  elevationMouseMove = (lat, lng, event) => {
    const latLng = this.elevationMouseCommon(lat, lng);
    if (latLng && (!this.hasPreciseMouse || event.shiftKey)) {
      this.map.panTo(latLng);
    }
  };

  elevationMouseClick = (lat, lng) => {
    const latLng = this.elevationMouseCommon(lat, lng);
    if (latLng) {
      this.map.panTo(latLng);
    }
  };

  elevationMouseLeave = () => {
    this.elevationCurrentPoint = removeFromMap(this.elevationCurrentPoint, this.map);
  };

  addElevation = (stageId, minimal) => {
    const element = document.querySelector(`#stage-elevation-${stageId}`);
    if (!element) return;
    const points = this.points[stageId];
    if (!points) {
      // element.parentNode.remove();
      return;
    }
    const computedStyle = getComputedStyle(document.querySelector('.live-graph'));

    const axe = {
      show: false,
      ticks: { show: false },
      grid: { show: false },
    };
    const opts = {
      cursor: {
        y: false,
        dataIdx: (u, seriesIndex, dataIndex) => {
          if (seriesIndex !== 0) return dataIndex;
          const lat = u.data[2][dataIndex];
          const lng = u.data[3][dataIndex];
          this.elevationMouseMove(lat, lng, u.cursor.event);
          return dataIndex;
        },
      },
      hooks: {
        ready: [
          (u) => {
            u.root.addEventListener('click', () => {
              const dataIndex = u.cursor.idx;
              const lat = u.data[2][dataIndex];
              const lng = u.data[3][dataIndex];
              this.elevationMouseClick(lat, lng);
            });
          },
        ],
      },
      width: parseInt(computedStyle.getPropertyValue('width'), 10),
      height: parseInt(computedStyle.getPropertyValue('height'), 10),
      scales: { x: { time: false } },
      axes: [axe, minimal ? axe : {}, axe, axe],
      series: [
        {
          points: { show: false },
          label: '-',
          class: 'uplot-label-km',
          value: (self, v) => (v ? `${v.toFixed(2)}km` : ''),
        },
        {
          points: { show: false },
          spanGaps: true,
          label: '-',
          class: 'uplot-label-el',
          value: (self, v) => (v ? `${v.toFixed(0)}m` : ''),
          stroke: 'rgb(13, 110, 253)',
          width: 2,
          fill: minimal ? 'rgba(13, 110, 253, 0.3)' : null,
        }, {
          show: false,
          label: '-',
          class: 'uplot-label-hidden',
        }, {
          show: false,
          label: '-',
          class: 'uplot-label-hidden',
        },
      ],
    };

    if (!minimal) {
      opts.hooks.draw = [
        (u) => {
          if (!this.activeChartPosition) return;

          const dpr = window.devicePixelRatio || 1;
          const { ctx } = u;
          const margin = 15 * dpr;
          const x = this.activeChartPosition * (u.width * dpr);

          ctx.save();
          ctx.strokeStyle = '#258656';
          ctx.lineWidth = 2 * dpr;
          ctx.setLineDash([4 * dpr, 4 * dpr]);
          ctx.beginPath();
          ctx.moveTo(x, margin);
          ctx.lineTo(x, (u.height * dpr) - margin);
          ctx.stroke();
          ctx.restore();
        },
      ];
    }

    const data = this.elevationPrepareData(stageId);
    element.innerHTML = '';
    const chart = new uPlot(opts, data, element);

    if (minimal) return;

    this.activeChart = chart;
  };

  updateElevationGraph = (position) => {
    if (!this.activeChart) return;
    this.activeChartPosition = position;
    this.activeChart.redraw();
  };

  elevationPrepareData = (stageId) => {
    const points = this.points[stageId];
    if (!points) return [];
    const pointsLatLng = points.map((p) => new LatLng(p.lat, p.lon, p.el));
    const accumulatedLengths = L.GeometryUtil.accumulatedLengths(pointsLatLng);
    const max = accumulatedLengths.length;
    const seriesX = [];
    const seriesAlt = [];
    const seriesLat = [];
    const seriesLng = [];
    for (let i = 0; i < max; i += 1) {
      const p = pointsLatLng[i];
      seriesX.push(accumulatedLengths[i] / 1000);
      seriesAlt.push(p.alt);
      seriesLat.push(p.lat);
      seriesLng.push(p.lng);
    }

    return [seriesX, seriesAlt, seriesLat, seriesLng];
  };

  // Helpers

  preventWarnings = () => {
    // Targets
    this.mapTarget = null;
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
