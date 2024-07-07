// noinspection JSUnusedGlobalSymbols

import L from 'leaflet';
import '@elfalem/leaflet-curve';
import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';
import Routing from 'fos-router';
import { iconSymbol, removeFromMap } from '../helpers';
import '../js/leaflet-double-touch-drag-zoom';

export default class extends Controller {
  static targets = [
    'myLivePosition',
  ];

  static values = {
    options: Object,
    tiles: Array,
    translations: Object,
  };

  connect = () => {
    this.diaryEntries = {};
    this.cache = {};

    // Even if Stimulus has Outlets, we use this mechanism to export method for external use
    window.diaryController = {
      addDiaryEntry: this.addDiaryEntry,
      updateDiaryEntry: this.updateDiaryEntry,
      removeAllDiaryEntries: this.removeAllDiaryEntries,
    };

    window.mapCommonController.mapClickActionDelegate(this.mapClickAction);
  };

  disconnect() {
    this.mapLocationRemoveHandler();
  }

  map = () => window.mapCommonController.map;

  // Actions

  myLivePositionAction = () => {
    this.myLivePositionTarget.innerHTML = '⏳';
    this.map().on('locationfound', this.mapLocationFoundHandler);
    this.map().on('locationerror', this.mapLocationErrorHandler);
    this.map().locate({ setView: true, maxZoom: 16, enableHighAccuracy: true });
  };

  diaryEntryClickAction = (e) => {
    const { id } = e.params;
    sidebarController.switchToMapAction();
    const marker = this.diaryEntries[id];
    this.map().panTo(marker.getLatLng());
    // if we open the popup right after the panTo it won't place the marker at the map center
    setTimeout(() => marker.openPopup(), 300);
  };

  newPhotoAction = () => {
    const center = this.map().getCenter();
    Turbo.visit(
      Routing.generate('photo_new', { lat: center.lat, lon: center.lng, trip: tripId }),
      { frame: 'diaryEntry-new' },
    );
  };

  mapClickAction = (e) => {
    Turbo.visit(
      Routing.generate('diaryEntry_new', { lat: e.latlng.lat, lon: e.latlng.lng, trip: tripId }),
      { frame: 'diaryEntry-new' },
    );

    sidebarController.switchToSidebarAction(true);
  };

  // Marker related

  addDiaryEntry = (id, lat, lon, symbol, popup) => {
    this.diaryEntries[id] = L.marker([parseFloat(lat), parseFloat(lon)], {
      icon: iconSymbol(symbol),
      draggable: true,
    })
      .bindPopup(popup)
      .on('dragend', (event) => {
        const marker = event.target;
        const position = marker.getLatLng();
        Turbo.visit(
          Routing.generate('diaryEntry_move', {
            id, lat: position.lat, lon: position.lng, trip: tripId,
          }),
          { frame: 'sidebar-diaryEntries' },
        );
      })
      .addTo(this.map());
  };

  updateDiaryEntry = (id, symbol, popin) => {
    const marker = this.diaryEntries[id];
    if (!marker) {
      return;
    }
    marker.setIcon(iconSymbol(symbol));
    marker.getPopup().setContent(popin);
  };

  removeAllDiaryEntries = () => {
    for (const index in this.diaryEntries) {
      removeFromMap(this.diaryEntries[index], this.map());
    }
  };

  // Event based

  mapLocationRemoveHandler = () => {
    this.map().off('locationfound', this.mapLocationFoundHandler);
    this.map().off('locationerror', this.mapLocationErrorHandler);
  };

  mapLocationErrorHandler = (e) => {
    this.mapLocationRemoveHandler();
    this.myLivePositionTarget.innerHTML = '⚪️';
    // eslint-disable-next-line no-alert
    window.alert(e.message);
  };

  mapLocationFoundHandler = (e) => {
    this.mapLocationRemoveHandler();

    this.myLivePositionTarget.innerHTML = '🔵';
    mapCommonController.removeAllElements();

    const divElement = document.createElement('div');
    divElement.classList.add('d-flex', 'flex-column');

    const marker = mapCommonController.addElement(e.latlng.lat, e.latlng.lng, divElement, false);

    const addDiaryEntry = document.createElement('a');
    addDiaryEntry.classList.add('btn', 'btn-outline-primary', 'btn-sm', 'mb-2');
    addDiaryEntry.innerHTML = 'Add to my Diary';
    addDiaryEntry.addEventListener(
      'click',
      () => this.mapLocationFoundAddDiary(marker),
    );

    const updateProgress = document.createElement('a');
    updateProgress.classList.add('btn', 'btn-outline-primary', 'btn-sm', 'mb-2');
    updateProgress.innerHTML = 'Add progress';
    updateProgress.addEventListener(
      'click',
      () => this.mapLocationFoundUpdateProgress(marker),
    );

    const deleteElement = document.createElement('a');
    deleteElement.innerHTML = 'Delete this marker';
    deleteElement.classList.add('btn', 'btn-outline-primary', 'btn-sm');
    deleteElement.addEventListener('click', () => {
      mapCommonController.removeAllElements();
    });

    divElement.appendChild(addDiaryEntry);
    divElement.appendChild(updateProgress);
    divElement.appendChild(deleteElement);

    marker.dragging.enable();
    marker.openPopup();
  };

  mapLocationFoundAddDiary = (marker) => {
    const latLng = marker.getLatLng();
    Turbo.visit(
      Routing.generate('diaryEntry_new', { lat: latLng.lat, lon: latLng.lng, trip: tripId }),
      { frame: 'diaryEntry-new' },
    );

    sidebarController.switchToSidebarAction(true);
    mapCommonController.removeAllElements();
  };

  mapLocationFoundUpdateProgress = (marker) => {
    const latLng = marker.getLatLng();
    Turbo.visit(
      Routing.generate('diaryEntry_update_progress', { lat: latLng.lat, lon: latLng.lng, trip: tripId }),
      { frame: 'sidebar-diaryEntries' },
    );

    mapCommonController.removeAllElements();
  };

  // Helpers

  // This method is here to define elements so eslint won't complain and the IDE will complete
  preventWarnings = () => {
    // Targets
    this.myLivePositionTarget = null;
    // Values
    this.translationsValue = {
      clickMapToAdd: null,
      orHereToCancel: null,
      more: null,
      less: null,
    };
  };
}
