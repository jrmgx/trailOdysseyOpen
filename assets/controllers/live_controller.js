// noinspection JSUnusedGlobalSymbols

import * as Turbo from '@hotwired/turbo';
import L from 'leaflet';
import '@elfalem/leaflet-curve';
import { Controller } from '@hotwired/stimulus';
import Routing from 'fos-router';
import {
  iconSymbol, curve, iconGpsPoint, iconGpsCompass, removeFromMap,
} from '../helpers';
import '../js/leaflet-double-touch-drag-zoom';

export default class extends Controller {
  static targets = [
    'backButton',
    'progressText',
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
    tiles: Array,
    translations: Object,
  };

  connect = () => {
    this.stages = {};
    this.interests = {};
    this.extras = [];
    this.cache = {};
    this.liveMarker = null;
    this.compassMarker = null;
    this.firstLocation = true;
    this.activeStage = null;
    this.currentLat = null;
    this.currentLng = null;
    this.findPathCloseToPointOnce = false;

    // Move back button to zoom control (not ideal but acceptable)
    const topLeft = document.querySelector('.leaflet-control-zoom.leaflet-bar.leaflet-control');
    if (topLeft) {
      topLeft.appendChild(this.backButtonTarget);
      document.querySelector('.leaflet-control-zoom-in').style.display = 'none';
      document.querySelector('.leaflet-control-zoom-out').style.display = 'none';
    }
    this.backButtonTarget.classList.remove('hide');

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
      addStage: this.addStage,
      addExtra: this.addExtra,
      addInterest: this.addInterest,
      startLiveTracking: this.startLiveTracking,
    };
  };

  disconnect() {
    this.mapLocationRemoveHandler();
    this.compassOff();
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
      this.liveMarker = removeFromMap(this.liveMarker, this.map());
    }
  };

  mapLocationErrorHandler = (e) => {
    // eslint-disable-next-line no-console
    console.error(e);
    // window.alert(e.message);
  };

  mapLocationFoundHandler = (e) => {
    // const radius = e.accuracy;
    this.currentLat = e.latlng.lat;
    this.currentLng = e.latlng.lng;

    if (this.liveMarker) {
      this.liveMarker.setLatLng(e.latlng);
    } else {
      this.liveMarker = L.marker([e.latlng.lat, e.latlng.lng], { icon: iconGpsPoint })
        .on('click', this.compassOn)
        .addTo(this.map());
    }

    this.updateLiveElements(e.latlng);

    if (this.firstLocation) {
      this.firstLocation = false;
      this.map().locate({ setView: false, watch: true, enableHighAccuracy: true });
    }
  };

  updateLiveElements = (latlng) => {
    const [
      stageIndex,
      path,
      distance,
      closestToPoint,
    ] = mapCommonController.findPathCloseToPoint(latlng);
    if (stageIndex === null) {
      // eslint-disable-next-line no-console
      console.error('findPathCloseToPoint / updateLiveElements error');
      return;
    }

    const correctStage = `${this.activeStage}` === `${stageIndex}`;
    if (!correctStage && !this.findPathCloseToPointOnce) {
      this.setActiveStage(stageIndex);
      Turbo.visit(
        Routing.generate('live_show_stage', { stage: stageIndex, trip: tripId }),
        { frame: 'live-stage', action: 'advance' },
      );
      return;
    }

    this.findPathCloseToPointOnce = true;

    if (!correctStage) {
      return;
    }

    // noinspection JSUnresolvedReference
    const percentOfPath = L.GeometryUtil.locateOnLine(this.map(), path, closestToPoint);
    mapCommonController.updateElevationGraph(percentOfPath);

    if (this.hasProgressTextTarget) {
      const distanceKm = (distance / 1000) * percentOfPath;
      this.progressTextTarget.innerText = `${distanceKm.toFixed(2)} km (${Math.round(percentOfPath * 100)}%)`;
    }
  };

  // State

  myLivePositionAction = () => {
    if (!this.currentLat) return;
    Turbo.visit(
      Routing.generate('diaryEntry_new', { lat: this.currentLat, lon: this.currentLng, trip: tripId }),
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
    this.disconnect();
    this.activeStage = stageId;
    this.firstLocation = true;
  };

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
    Turbo.visit(
      Routing.generate('live_show_stage', { stage: stageId, trip: tripId }),
      { frame: 'live-stage' },
    );
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

  addInterest = (id, lat, lon, symbol, popup) => {
    this.interests[id] = L.marker([parseFloat(lat), parseFloat(lon)], {
      icon: iconSymbol(symbol),
      draggable: false,
    })
      .bindPopup(popup)
      .addTo(this.map());
  };

  // Event based

  // Compass

  compassOn = () => {
    window.addEventListener('compassneedscalibration', () => {
      // eslint-disable-next-line no-alert
      alert('Your compass needs calibrating!');
    });

    if (
      typeof (DeviceOrientationEvent) !== 'undefined'
        && typeof (DeviceOrientationEvent.requestPermission) === 'function'
    ) {
      /* iPhoneOS, must ask interactively */
      DeviceOrientationEvent.requestPermission().then((permission) => {
        if (permission !== 'granted') {
          // eslint-disable-next-line no-console
          console.error(`Compass permission != granted: ${permission}`);
          return;
        }
        this.compassActivate();
      }, (reason) => {
        // eslint-disable-next-line no-console
        console.error(`Error activating compass: ${reason}`);
      });
    } else {
      this.compassActivate();
    }
  };

  compassActivate = () => {
    window.addEventListener('deviceorientation', this.compassHandler);
    if (this.compassMarker) {
      this.compassMarker.setLatLng(L.latLng(this.currentLat, this.currentLng));
    } else {
      this.compassMarker = L.marker([this.currentLat, this.currentLng], { icon: iconGpsCompass })
        .addTo(this.map());
    }
  };

  compassHandler = (e) => {
    let angle;
    // CSS rotate is clockwise and the image of the compass points up by default
    // noinspection JSUnresolvedReference
    if (e.webkitCompassHeading) {
      // https://developer.apple.com/documentation/webkitjs/deviceorientationevent/1804777-webkitcompassheading
      // Direction values are measured in degrees starting at due north
      // and continuing clockwise around the compass.
      // Thus, north is 0 degrees, east is 90 degrees, south is 180 degrees, and so on.
      // A negative value indicates an invalid direction.
      // => No correction needed
      angle = e.webkitCompassHeading;
    } else if (e.alpha) {
      // https://developer.mozilla.org/en-US/docs/Web/API/Window/deviceorientation_event#event_properties
      // A number representing the motion of the device around the z axis,
      // express in degrees with values ranging from 0 (inclusive) to 360 (exclusive).
      // -or- The alpha read-only property of the DeviceOrientationEvent interface returns
      // the rotation of the device around the Z axis; that is, the number of degrees
      // by which the device is being twisted around the center of the screen.
      // https://developer.mozilla.org/en-US/docs/Web/API/Device_orientation_events/Orientation_and_motion_data_explained#alpha
      // The alpha angle is 0° when top of the device is pointed directly toward
      // the Earth's north pole, and increases as the device is rotated counterclockwise.
      // As such, 90° corresponds with pointing west, 180° with south, and 270° with east.
      // => Correction, basically invert the rotation direction
      angle = 360 - e.alpha;
    } else {
      // eslint-disable-next-line no-console
      console.error('Orientation angle not found', e);
    }

    angle = Math.round(angle);

    // Min angle deviation before rotate
    if (angle % 2 === 0) {
      this.compassSetAngle(angle);
    }
  };

  compassSetAngle = (angle) => {
    if (!this.compassMarker) {
      return;
    }

    this.compassMarker.setLatLng(new L.LatLng(this.currentLat, this.currentLng));

    // Accessing private property
    // eslint-disable-next-line no-underscore-dangle
    const iconImgEl = this.compassMarker._icon;
    // CSS rotate is clockwise and the image of the compass points up by default
    iconImgEl.style.zIndex = '200'; // Marker default is 333
    iconImgEl.style.transformOrigin = 'center';
    iconImgEl.style.transform = this.transformRotate(iconImgEl.style.transform, angle);
  };

  compassOff = () => {
    window.removeEventListener('deviceorientation', this.compassHandler);
    if (this.compassMarker) {
      this.compassMarker = removeFromMap(this.compassMarker, this.map());
    }
  };

  // Helpers

  /**
   * Given a transform CSS value, update only the rotation part of it.
   */
  transformRotate = (transform, angle) => {
    const r = `rotate(${angle}deg)`;
    if (transform.length === 0) {
      return r;
    }

    const parts = transform.split(' ').filter((p) => !p.startsWith('rotate'));
    parts.push(r);

    return parts.join(' ');
  };

  preventWarnings = () => {
    // Targets
    this.liveBarTarget = null;
    this.liveBarSelectTarget = null;
    this.liveBarGraphTarget = null;
    this.liveBarButtonTarget = null;
    this.backButtonTarget = null;
    this.myLivePositionTarget = null;
    this.diaryEntryNewContainerTarget = null;
    this.progressTextTarget = null;
    this.hasProgressTextTarget = null;
    // Values
    this.translationsValue = {
      clickMapToAdd: null,
      orHereToCancel: null,
      more: null,
      less: null,
    };
  };
}
