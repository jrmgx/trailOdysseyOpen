// noinspection JSUnusedGlobalSymbols

import L from 'leaflet';
import { Controller } from '@hotwired/stimulus';
import { LatLng } from 'leaflet/src/geo';
import 'leaflet-geometryutil';
import '@elfalem/leaflet-curve';
import '../js/leaflet-double-touch-drag-zoom';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';
import { markerDefaultIcon, removeFromMap } from '../helpers';
import { flattenJsonDataToDotNotation, jsonToHtml } from '../jsonToHtml';

export default class extends Controller {
  static targets = [
    'map',
    'containerProgress',
    'geoElementForm',
    'providerSelect',
  ];

  static values = {
    options: Object,
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
    this.activeGeoJson = {}; // Key is the tiles url template
    this.abortFetch = null;
    this.activeChart = null;
    this.isPublic = !!this.isPublicValue;
    this.isLive = !!this.isLiveValue;
    this.hasPreciseMouse = matchMedia('(pointer:fine)').matches;

    // Init map

    this.proxyLayers = {};
    const baseLayers = {};
    const overlayLayers = {};
    const firstLayer = [];

    for (const tiles of this.tilesValue) {
      // noinspection JSUnresolvedReference
      const tilesUrl = this.isLive || tiles.useProxy ? tiles.proxyUrl : tiles.url;

      if (tiles.geoJson) {
        const currentLayer = L.geoJSON(null, {
          onEachFeature: this.onEachFeature.bind(tiles),
          pointToLayer: this.pointToLayer.bind(tiles),
        });
        currentLayer.on('add', () => {
          this.activateGeoJson(currentLayer, tiles);
        });
        currentLayer.on('remove', () => {
          this.deactivateGeoJson(currentLayer, tiles);
        });
        overlayLayers[tiles.name] = currentLayer;
      } else {
        const currentLayer = L.tileLayer(tilesUrl, {
          attribution: tiles.description || '',
        });
        currentLayer.id = tiles.id;
        if (tiles.overlay) {
          overlayLayers[tiles.name] = currentLayer;
        } else {
          if (firstLayer.length === 0) {
            firstLayer.push(currentLayer);
          }
          baseLayers[tiles.name] = currentLayer;
          if (!this.isLive) {
            // noinspection JSUnresolvedReference
            this.proxyLayers[tiles.id] = L.tileLayer(tiles.proxyUrl);
          }
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

    this.map.on('moveend', this.fetchGeoJson);

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
    if (!arrayOfPoints || arrayOfPoints.length < 2) return;
    const polyline = L.polyline(arrayOfPoints);
    this.map.fitBounds(polyline.getBounds());
  };

  // Event based

  updateSearchProvider = () => {
    if (!this.hasProviderSelectTarget) return;
    const form = this.geoElementFormTarget;
    form.querySelectorAll('.provider').forEach((el) => {
      el.classList.add('hide');
    });
    form.querySelector(`.provider-${this.providerSelectTarget.value}`).classList.remove('hide');
  };

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
    if (!points) return;
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
      interactive: false,
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
      // noinspection JSUnresolvedReference
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

    if (!element.style.width) {
      const computedStyle = getComputedStyle(document.querySelector('.live-graph'));
      element.style.width = `${parseInt(computedStyle.getPropertyValue('width'), 10) - 16}px`; // margin * 2
      element.style.height = `${parseInt(computedStyle.getPropertyValue('height'), 10)}px`;
    }

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
      width: parseInt(element.style.width, 10),
      height: parseInt(element.style.height, 10),
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
          show: false, // Latitudes
          label: '-',
          class: 'uplot-label-hidden',
        }, {
          show: false, // Longitudes
          label: '-',
          class: 'uplot-label-hidden',
        },
      ],
    };

    const data = this.elevationPrepareData(stageId);
    element.innerHTML = '';
    const chart = new uPlot(opts, data, element);

    if (minimal) return;

    this.activeChart = chart;
  };

  updateElevationGraph = (percentage, distance) => {
    if (!this.activeChart) return;
    const px = this.activeChart.valToPos(percentage * (distance / 1000), 'x');
    const uWrap = document.querySelector('.uplot .u-wrap');
    const uOver = uWrap.querySelector('.u-over');
    let uProgress = uWrap.querySelector('.u-progress');
    if (!uProgress) {
      uProgress = document.createElement('div');
      uProgress.classList.add('u-progress');
      uProgress.style.top = uOver.style.top;
      uProgress.style.left = uOver.style.left;
      uProgress.style.height = uOver.style.height;
      uWrap.appendChild(uProgress);
    }
    uProgress.style.width = `${px}px`;
  };

  elevationPrepareData = (stageId) => {
    const points = this.points[stageId];
    if (!points) return [];
    const pointsLatLng = points.map((p) => new LatLng(p.lat, p.lon, p.el));
    // noinspection JSUnresolvedReference
    const accumulatedLengths = L.GeometryUtil.accumulatedLengths(pointsLatLng);
    const max = accumulatedLengths.length;
    // We update the distance with this calculation instead of the backend info
    // as this one is used for the graph
    this.pathDistances[stageId] = accumulatedLengths[max - 1];
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

  // Geo JSON

  activateGeoJson = (layer, tiles) => {
    this.activeGeoJson[tiles.url] = [layer, tiles];
    this.fetchGeoJson();
  };

  deactivateGeoJson = (layer, tiles) => {
    delete (this.activeGeoJson[tiles.url]);
  };

  fetchGeoJson = () => {
    if (Object.entries(this.activeGeoJson).length <= 0) {
      return;
    }
    const coords = this.getTileCoordinates();
    if (this.abortFetch) {
      this.abortFetch.abort(); // Abort previous fetch
    }
    this.abortFetch = new AbortController();
    const { signal } = this.abortFetch;
    for (const url in this.activeGeoJson) {
      const [layer, tiles] = this.activeGeoJson[url];
      layer.clearLayers(); // Clear previous fetch
      // Bbox
      if (tiles.proxyUrl.match(/bbox$/)) {
        const bbox = this.getBoundingBox();
        const tileUrl = `${tiles.proxyUrl}/${bbox}`;

        fetch(tileUrl, { signal })
          .then((response) => response.json())
          .then((data) => {
            if (data.features) {
              layer.addData(data);
            }
          })
          .catch(() => {
            // Most likely a request abort
          });
      } else {
        // XYZ
        for (const { x, y, z } of coords) {
          const tileUrl = tiles.proxyUrl.replace('{x}', x).replace('{y}', y).replace('{z}', z);

          fetch(tileUrl, { signal })
            .then((response) => response.json())
            .then((data) => {
              if (data.features) {
                layer.addData(data);
              }
            })
            .catch(() => {
              // Most likely a request abort
            });
        }
      }
    }
  };

  // Geo JSON features

  /**
   * This is not an arrow function so we can bind a new this to it
   */
  onEachFeature = function (feature, layer) {
    const tiles = this;
    const flat = flattenJsonDataToDotNotation(feature);
    let popup;
    const geoJsonHtml = JSON.parse(tiles.geoJsonHtml);
    if (geoJsonHtml && geoJsonHtml.popup) {
      popup = jsonToHtml(geoJsonHtml.popup, flat);
    } else {
      popup = document.createElement('div');
      const ul = document.createElement('ul');
      for (const k in flat) {
        const v = flat[k];
        const li = document.createElement('li');
        li.textContent = `${k}: ${v}`;
        ul.appendChild(li);
      }
      popup.appendChild(ul);
    }
    layer.bindPopup(popup);
  };

  /**
   * This is not an arrow function so we can bind a new this to it
   */
  pointToLayer = function (feature, latlng) {
    const tiles = this;
    const flat = flattenJsonDataToDotNotation(feature);
    const geoJsonHtml = JSON.parse(tiles.geoJsonHtml);
    if (geoJsonHtml && geoJsonHtml.marker) {
      const html = jsonToHtml(geoJsonHtml.marker, flat);
      const icon = L.divIcon({ html });
      return L.marker(latlng, { icon });
    }
    return L.circleMarker(latlng);
  };

  // Helpers

  getBoundingBox = () => {
    const bounds = this.map.getBounds();
    const southWest = bounds.getSouthWest();
    const northEast = bounds.getNorthEast();

    return [
      southWest.lng.toFixed(6),
      southWest.lat.toFixed(6),
      northEast.lng.toFixed(6),
      northEast.lat.toFixed(6),
    ].join(',');
  };

  getTileCoordinates = () => {
    const z = this.map.getZoom();
    const pixelBounds = this.map.getPixelBounds();
    const tileSize = 256; // TODO this.map.getTileSize();

    // Get the tile range
    const tileBounds = L.bounds(
      pixelBounds.min.divideBy(tileSize).floor(),
      pixelBounds.max.divideBy(tileSize).floor(),
    );

    const tiles = [];

    // Loop through the tile range and store the coordinates
    for (let { x } = tileBounds.min; x <= tileBounds.max.x; x += 1) {
      for (let { y } = tileBounds.min; y <= tileBounds.max.y; y += 1) {
        tiles.push({ x, y, z });
      }
    }

    return tiles;
  };

  preventWarnings = () => {
    // Targets
    this.mapTarget = null;
    this.geoElementFormTarget = null;
    this.providerSelectTarget = null;
    this.hasProviderSelectTarget = null;
    this.containerProgressTarget = null;
    // Values
    this.cacheNameValue = null;
    this.isPublicValue = null;
    this.isLiveValue = null;
    this.optionsValue = { center: { lat: null, lon: null } };
    this.tilesValue = null;
    this.translationsValue = {
      clickMapToAdd: null,
      orHereToCancel: null,
    };
  };
}
