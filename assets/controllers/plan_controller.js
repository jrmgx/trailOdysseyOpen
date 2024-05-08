// noinspection JSUnusedGlobalSymbols

import L from 'leaflet';
import '@elfalem/leaflet-curve';
import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';
import {
  curve, iconSymbol, addLatLonToUrl,
} from '../helpers';
import '../js/leaflet-double-touch-drag-zoom';

export default class extends Controller {
  static targets = [
    'totalDistance',
    'sidebarStages',
    'sidebarInterests',
    'offlineButton',
  ];

  static values = {
    urls: Object,
  };

  connect = () => {
    this.stages = {};
    this.routings = {};
    this.interests = {};
    this.extras = [];

    this.updateOfflineButtonStatus();

    // Export method for external use
    window.planController = {
      addStage: this.addStage,
      updateStage: this.updateStage,
      addRouting: this.addRouting,
      updateRouting: this.updateRouting,
      addExtra: this.addExtra,
      removeAllStageRoutingExtra: this.removeAllStageRoutingExtra,
      addInterest: this.addInterest,
      updateInterest: this.updateInterest,
      removeAllInterests: this.removeAllInterests,
      updateDistance: this.updateDistance,
      drawBoundingBox: this.drawBoundingBox, // For debug
      getRoutings: this.getRoutings(),
    };

    window.mapCommonController.mapClickActionDelegate(this.mapClickAction);
  };

  updateOfflineButtonStatus = () => {
    for (let offlineButton of this.offlineButtonTargets) {
      if (mapCommonController.getIsOffline(offlineButton.dataset.planIdParam)) {
        offlineButton.classList.remove('btn-outline-secondary');
        offlineButton.classList.add('btn-success');
      }
    }
  }

  map = () => window.mapCommonController.map;

  getRoutings = () => this.routings;

  // Actions

  tabSwitchAction = (e) => {
    const { tab } = e.params;
    this.tabSwitch(tab);
  };

  tabSwitch = (tab) => {
    if (tab === 'stages') {
      this.sidebarStagesTarget.classList.remove('hide');
      this.sidebarInterestsTarget.classList.add('hide');
      document.querySelector('.tab-stages').classList.add('active');
      document.querySelector('.tab-interests').classList.remove('active');
    } else {
      this.sidebarStagesTarget.classList.add('hide');
      this.sidebarInterestsTarget.classList.remove('hide');
      document.querySelector('.tab-stages').classList.remove('active');
      document.querySelector('.tab-interests').classList.add('active');
    }
  };

  centerMapAction = (e) => {
    e.stopImmediatePropagation();
    const latLngs = [];
    const stageIds = Object.keys(this.stages);
    for (const stageId of stageIds) {
      const stage = this.stages[stageId];
      latLngs.push(stage.getLatLng());
    }

    this.map().fitBounds(L.latLngBounds(latLngs));
    sidebarController.showVisibilityAction();
  };

  stageClickAction = (e) => {
    const { id } = e.params;
    sidebarController.switchToMapAction();
    const marker = this.stages[id];
    this.map().panTo(marker.getLatLng());
    // if we open the popup right after the panTo it won't place the marker at the map center
    setTimeout(() => marker.openPopup(), 300);
  };

  routingClickAction = (e) => {
    const { id } = e.params;
    sidebarController.switchToMapAction();
    const line = this.routings[id];
    this.map().fitBounds(line.getBounds());
    // setTimeout(() => line.openPopup(), 300);
  };

  routingOfflineAction = (e) => {
    const { id } = e.params;
    mapCommonController.downloadOfflinePoints(id, this.routings[id].getLatLngs(), this.updateOfflineButtonStatus);
  };

  interestClickAction = (e) => {
    const { id } = e.params;
    sidebarController.switchToMapAction();
    const marker = this.interests[id];
    this.map().panTo(marker.getLatLng());
    // if we open the popup right after the panTo it won't place the marker at the map center
    setTimeout(() => marker.openPopup(), 300);
  };

  mapClickAction = (e, actionPinActiveFor) => {
    if (!actionPinActiveFor) return;

    let url = this.urlsValue.stageNew;
    let frame = 'stage-new';
    if (actionPinActiveFor === 'interest') {
      url = this.urlsValue.interestNew;
      frame = 'interest-new';
    }

    Turbo.visit(addLatLonToUrl(e.latlng.lat, e.latlng.lng, url), { frame });

    sidebarController.switchToSidebarAction(true);
  };

  // Marker related

  addStage = (id, lat, lon, symbol, popin) => {
    this.stages[id] = L.marker([parseFloat(lat), parseFloat(lon)], {
      icon: iconSymbol(symbol),
      draggable: true,
    })
      .bindPopup(popin)
      .on('dragend', (event) => {
        const marker = event.target;
        const position = marker.getLatLng();
        Turbo.visit(
          addLatLonToUrl(position.lat, position.lng, this.urlsValue.stageMove).replace('/0/', `/${id}/`),
          { frame: 'sidebar-stages' },
        );
      })
      .addTo(this.map());
  };

  updateStage = (id, popup) => {
    const marker = this.stages[id];
    if (!marker) {
      return;
    }
    marker.getPopup().setContent(popup);
  };

  addRouting = (id, startLat, startLon, finishLat, finishLon, distance, mode, el, points) => {
    const weight = 6;
    const color = 'red';
    const routingPopupContent = `${distance} km ${mode}<br>${el}`;
    if (points) {
      const latLon = [];
      for (const p of points) {
        latLon.push([p.lat, p.lon]);
      }
      const { length } = latLon;
      if (length < 2) {
        return;
      }
      this.routings[id] = L.polyline(latLon, { interactive: false, color, weight })
        .setStyle({ cursor: 'default' })
        .bindPopup(routingPopupContent)
        .addTo(this.map());
    } else {
      const startPoint = L.latLng(parseFloat(startLat), parseFloat(startLon));
      const endPoint = L.latLng(parseFloat(finishLat), parseFloat(finishLon));
      this.routings[id] = curve(startPoint, endPoint, { color: 'black', weight: 2 })
        .bindPopup(routingPopupContent)
        .addTo(this.map());
    }
  };

  updateRouting = (id, startLat, startLon, finishLat, finishLon, distance, mode, el, points) => {
    const line = this.routings[id];
    if (!line) {
      return;
    }
    line.remove();
    this.addRouting(id, startLat, startLon, finishLat, finishLon, distance, mode, el, points);
    mapCommonController.removeOffline(id);
  };

  addExtra = (startLat, startLon, finishLat, finishLon, distance) => {
    const routingPopupContent = `+ ${distance} km<br>● ➞ ●`;
    const startPoint = L.latLng(parseFloat(startLat), parseFloat(startLon));
    const endPoint = L.latLng(parseFloat(finishLat), parseFloat(finishLon));
    this.extras.push(curve(startPoint, endPoint, { color: 'black', weight: 2 })
      .bindPopup(routingPopupContent)
      .addTo(this.map()));
  };

  removeAllStageRoutingExtra = () => {
    for (const index in this.stages) {
      let stage = this.stages[index];
      stage.remove();
      stage = null;
    }
    for (const index in this.routings) {
      let routing = this.routings[index];
      routing.remove();
      routing = null;
    }
    for (let extra of this.extras) {
      extra.remove();
      extra = null;
    }
  };

  addInterest = (id, lat, lon, symbol, popup) => {
    this.interests[id] = L.marker([parseFloat(lat), parseFloat(lon)], {
      icon: iconSymbol(symbol),
      draggable: true,
    })
      .bindPopup(popup)
      .on('dragend', (event) => {
        const marker = event.target;
        const position = marker.getLatLng();
        Turbo.visit(
          addLatLonToUrl(position.lat, position.lng, this.urlsValue.interestMove).replace('/0/', `/${id}/`),
          { frame: 'sidebar-interests' },
        );
      })
      .addTo(this.map());
  };

  updateInterest = (id, symbol, popin) => {
    const marker = this.interests[id];
    if (!marker) {
      return;
    }
    marker.setIcon(iconSymbol(symbol));
    marker.getPopup().setContent(popin);
  };

  removeAllInterests = () => {
    for (const index in this.interests) {
      let interest = this.interests[index];
      interest.remove();
      interest = null;
    }
  };

  // Event based

  updateDistance = (distance) => {
    this.totalDistanceTarget.innerHTML = `${distance}&nbsp;km`;
  };

  // Helpers

  // Debug

  drawBoundingBox = (json) => {
    const data = JSON.parse(json);
    L.polyline([
      [data.minLat, data.minLon],
      [data.maxLat, data.minLon],
      [data.maxLat, data.maxLon],
      [data.minLat, data.maxLon],
      [data.minLat, data.minLon],
    ], { color: 'red', weight: 2 })
      .addTo(this.map());
  };

  preventWarnings = () => {
    // Targets
    this.mapTarget = null;
    this.offlineButtonTargets = null;
    this.totalDistanceTarget = null;
    this.sidebarStagesTarget = null;
    this.sidebarInterestsTarget = null;
    // Values
    this.urlsValue = {
      mapSearch: null,
      stageNew: null,
      interestNew: null,
      photoNew: null,
      stageMove: null,
      interestMove: null,
      mapOption: null,
    };
  };
}
