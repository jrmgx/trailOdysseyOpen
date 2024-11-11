// noinspection JSUnusedGlobalSymbols

import L from 'leaflet';
import '@elfalem/leaflet-curve';
import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';
import Routing from 'fos-router';
import {
  curve, subPolyline, iconSymbol, markerDefaultIcon, removeFromMap,
} from '../helpers';
import '../js/leaflet-double-touch-drag-zoom';

export default class extends Controller {
  static targets = [
    'totalDistance',
    'sidebarStages',
    'sidebarInterests',
  ];

  static values = {};

  connect = () => {
    this.stages = {};
    this.routings = {};
    this.interests = {};
    this.extras = [];
    this.pointOnRouting = null;

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
    if (this.pointOnRouting) {
      this.pointOnRouting = removeFromMap(this.pointOnRouting, this.map());
    }

    if (!actionPinActiveFor) return;

    if (actionPinActiveFor === 'interest') {
      Turbo.visit(
        Routing.generate('interest_new', { lat: e.latlng.lat, lon: e.latlng.lng, trip: tripId }),
        { frame: 'interest-new' },
      );
    } else {
      Turbo.visit(
        Routing.generate('stage_new', { lat: e.latlng.lat, lon: e.latlng.lng, trip: tripId }),
        { frame: 'stage-new' },
      );
    }

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
          Routing.generate('stage_move', {
            lat: position.lat, lon: position.lng, id, trip: tripId,
          }),
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
    if (points) {
      const latLon = [];
      for (const p of points) {
        latLon.push([p.lat, p.lon]);
      }
      const { length } = latLon;
      if (length < 2) {
        return;
      }
      this.routings[id] = L.polyline(latLon, {
        interactive: true, color, weight, bubblingMouseEvents: false,
      })
        .setStyle({ cursor: 'default' })
        .on('click', (e) => {
          if (this.pointOnRouting) {
            const actual = L.GeometryUtil
              .closest(this.map(), this.routings[id], e.latlng, true);
            try {
              const sub = subPolyline(this.routings[id], this.pointOnRouting.getLatLng(), actual);
              const lengths = L.GeometryUtil.accumulatedLengths(sub);
              // eslint-disable-next-line no-alert
              alert(L.GeometryUtil.readableDistance(lengths[lengths.length - 1], 'metric'));
            } catch {
              // eslint-disable-next-line no-alert
              alert('Distance is only possible on the same stage.');
            }
          } else {
            const actual = L.GeometryUtil.closest(this.map(), this.routings[id], e.latlng, true);
            this.pointOnRouting = L.marker(actual, { icon: markerDefaultIcon }).addTo(this.map());
          }
        })
        .addTo(this.map());
    } else {
      const startPoint = L.latLng(parseFloat(startLat), parseFloat(startLon));
      const endPoint = L.latLng(parseFloat(finishLat), parseFloat(finishLon));
      this.routings[id] = curve(startPoint, endPoint, { color: 'black', weight: 2 })
        .addTo(this.map());
    }
  };

  updateRouting = (id, startLat, startLon, finishLat, finishLon, distance, mode, el, points) => {
    const line = this.routings[id];
    if (!line) {
      return;
    }
    removeFromMap(line, this.map());
    this.addRouting(id, startLat, startLon, finishLat, finishLon, distance, mode, el, points);
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
      removeFromMap(this.stages[index], this.map());
    }
    for (const index in this.routings) {
      removeFromMap(this.routings[index], this.map());
    }
    for (const extra of this.extras) {
      removeFromMap(extra, this.map());
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
          Routing.generate('interest_move', {
            lat: position.lat, lon: position.lng, id, trip: tripId,
          }),
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
      removeFromMap(this.interests[index], this.map());
    }
  };

  // Event based

  updateDistance = (distance) => {
    this.totalDistanceTarget.innerHTML = distance;
  };

  // Helpers

  // Debug

  drawBoundingBox = (json) => {
    const data = JSON.parse(json);
    // noinspection JSUnresolvedReference
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
    this.totalDistanceTarget = null;
    this.sidebarStagesTarget = null;
    this.sidebarInterestsTarget = null;
    // Values
    // ...
  };
}
