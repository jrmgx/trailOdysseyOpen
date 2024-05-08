// noinspection JSUnusedGlobalSymbols

import * as Turbo from '@hotwired/turbo';
import L from 'leaflet';
import '@elfalem/leaflet-curve';
import 'leaflet-compass';
import { Controller } from '@hotwired/stimulus';
import {
  iconSymbol, iconLive, curve, addLatLonToUrl,
} from '../helpers';
import '../js/leaflet-double-touch-drag-zoom';

export default class extends Controller {
  static targets = [
    'backButton',
    'liveBar',
    'liveBarContent',
    'liveBarSelect',
    'liveBarGraph',
    'liveBarButton',
    'myLivePosition',
    'diaryEntryNewContainer',
  ];

  static values = {
    options: Object,
    urls: Object,
    tiles: Array,
    translations: Object,
  };

  connect = () => {
    this.stages = {};
    this.extras = [];
    this.cache = {};
    this.liveMarker = null;
    this.firstLocation = true;
    this.activeStage = null;
    this.currentLat = null;
    this.currentLng = null;

    // Move back button to zoom control (not ideal but acceptable)
    const topLeft = document.querySelector('.leaflet-control-zoom.leaflet-bar.leaflet-control');
    if (topLeft) {
      topLeft.appendChild(this.backButtonTarget);
    }
    this.backButtonTarget.classList.remove('hide');

    const compass = new L.Control.Compass({ autoActive: false, showDigit: false });
    compass.addTo(this.map());
    const control = document.querySelector('.leaflet-control.leaflet-compass');
    control.remove();
    document.querySelector('.map-button-container').appendChild(control);
    control.classList.add('with-box-shadow-radius');

    // document.addEventListener('turbo:frame-load', this.turboFrameLoadEventHandler);
    // Because this event does not work on iOS
    // we use an observer and work/hack from here
    const diaryEntryNewFrame = document.getElementById('diaryEntry-new');
    const observer = new MutationObserver((m) => {
      const firstMutation = m[0];
      if (firstMutation.addedNodes.length > 0) {
        this.diaryEntryNewContainerTarget.classList.remove('d-none');
      } else {
        this.diaryEntryNewContainerTarget.classList.add('d-none');
        diaryEntryNewFrame.innerHTML = '';
      }
    });
    observer.observe(diaryEntryNewFrame, { childList: true });

    // Export method for external use
    window.liveController = {
      setActiveStage: this.setActiveStage,
      getActiveStage: this.getActiveStage,
      addStage: this.addStage,
      addExtra: this.addExtra,
      startLiveTracking: this.startLiveTracking,
    };
  };

  disconnect() {
    this.mapLocationRemoveHandler();
    this.map().stopLocate();
    // document.removeEventListener('turbo:frame-load', this.turboFrameLoadEventHandler);
  }

  map = () => window.mapCommonController.map;

  startLiveTracking = () => {
    this.mapLocationAddHandler();
    this.map().locate({ setView: true, maxZoom: 16, enableHighAccuracy: true });
  };

  mapLocationAddHandler = () => {
    this.map().on('locationfound', this.mapLocationFoundHandler);
    this.map().on('locationerror', this.mapLocationErrorHandler);
  };

  mapLocationRemoveHandler = () => {
    this.map().off('locationfound', this.mapLocationFoundHandler);
    this.map().off('locationerror', this.mapLocationErrorHandler);

    this.firstLocation = true;

    if (this.liveMarker) {
      this.liveMarker.remove();
    }
  };

  mapLocationErrorHandler = (e) => {
    // eslint-disable-next-line no-console
    console.log(e);
    // window.alert(e.message);
  };

  mapLocationFoundHandler = (e) => {
    // let radius = e.accuracy;
    this.currentLat = e.latlng.lat;
    this.currentLng = e.latlng.lng;

    if (this.liveMarker) {
      this.liveMarker.remove();
    }
    this.liveMarker = L.marker([e.latlng.lat, e.latlng.lng], { icon: iconLive })
      .addTo(this.map());

    if (!mapCommonController.findPathCloseToPoint(e.latlng)) {
      // return false; // Failed
    }

    if (this.firstLocation) {
      this.firstLocation = false;
      this.map().locate({ setView: false, watch: true, enableHighAccuracy: true });
    }
  };

  // State

  myLivePositionAction = () => {
    if (!this.currentLat) return;
    Turbo.visit(
      addLatLonToUrl(this.currentLat, this.currentLng, this.urlsValue.diaryEntryNew),
      { frame: 'diaryEntry-new' },
    );
  };

  // turboFrameLoadEventHandler = (e) => {
  //   if (e.originalTarget === this.diaryEntryNewContainerTarget.childNodes[0]) {
  //     if (this.diaryEntryNewContainerTarget.classList.contains('d-none')) {
  //       this.diaryEntryNewContainerTarget.classList.remove('d-none');
  //     } else {
  //       this.diaryEntryNewContainerTarget.classList.add('d-none');
  //     }
  //   }
  // };

  setActiveStage = (stageId) => {
    // Before changing stage we stop any location handler that could be live
    this.mapLocationRemoveHandler();
    this.activeStage = stageId;
  };

  getActiveStage = () => this.activeStage;

  // Actions

  centerMapAction = (e) => {
    e.stopImmediatePropagation();
    if (!this.currentLat) {
      return;
    }
    this.map().panTo(new L.LatLng(this.currentLat, this.currentLng));
    sidebarController.showVisibilityAction();
  };

  routingChangedAction = (e) => {
    const stageId = e.target.value;
    this.setActiveStage(stageId);
    Turbo.visit(this.urlsValue.liveShowStage.replace('/0', `/${stageId}`), { frame: 'live-stage' });
  };

  collapseAction = () => {
    if (parseInt(this.liveBarTarget.style.bottom + 0, 10) !== 0) {
      this.liveBarTarget.style.bottom = '0';
      this.liveBarSelectTarget.classList.remove('hide');
      this.liveBarGraphTarget.classList.remove('hide');
      this.liveBarButtonTarget.classList.remove('mt-2');
    } else {
      this.liveBarTarget.style.bottom = 'calc(var(--live-bar-height) * -1 + 2.75rem)';
      this.liveBarSelectTarget.classList.add('hide');
      this.liveBarGraphTarget.classList.add('hide');
      this.liveBarButtonTarget.classList.add('mt-2');
    }
  };

  // Marker related

  addStage = (id, lat, lon, symbol) => {
    this.stages[id] = L.marker([parseFloat(lat), parseFloat(lon)], {
      icon: iconSymbol(symbol),
      draggable: false,
    })
      .addTo(this.map());
  };

  addExtra = (startLat, startLon, finishLat, finishLon, distance) => {
    const routingPopupContent = `+ ${distance} km<br>● ➞ ●`;
    const startPoint = L.latLng(parseFloat(startLat), parseFloat(startLon));
    const endPoint = L.latLng(parseFloat(finishLat), parseFloat(finishLon));
    this.extras.push(curve(startPoint, endPoint, { color: 'black', weight: 2 })
      .bindPopup(routingPopupContent)
      .addTo(this.map()));
  };

  // Event based

  // Helpers

  preventWarnings = () => {
    // Targets
    this.liveBarTarget = null;
    this.liveBarSelectTarget = null;
    this.liveBarGraphTarget = null;
    this.liveBarButtonTarget = null;
    this.backButtonTarget = null;
    this.myLivePositionTarget = null;
    this.diaryEntryNewContainerTarget = null;
    // Values
    this.urlsValue = {
      mapSearch: null,
      stageNew: null,
      diaryEntryNew: null,
      photoNew: null,
      stageMove: null,
      diaryEntryMove: null,
      mapOption: null,
      liveShowStage: null,
    };
    this.translationsValue = {
      clickMapToAdd: null,
      orHereToCancel: null,
      more: null,
      less: null,
    };
  };
}
