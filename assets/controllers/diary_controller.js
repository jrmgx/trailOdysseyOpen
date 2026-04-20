// noinspection JSUnusedGlobalSymbols

// eslint-disable-next-line no-unused-vars
import L from 'leaflet';
import '@elfalem/leaflet-curve';
import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';
import Routing from 'fos-router';
import { iconSymbol, removeFromMap } from '../js/helpers';
import createDraggableMarker from '../js/draggableMarker';
import '../js/leaflet-double-touch-drag-zoom';

const DIARY_MAP_NEW_ENTRY_CLICK_MS = 280;

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
    this.pendingDiaryMapNewEntryTimeout = null;

    // Even if Stimulus has Outlets, we use this mechanism to export method for external use
    window.diaryController = {
      addDiaryEntry: this.addDiaryEntry,
      updateDiaryEntry: this.updateDiaryEntry,
      removeAllDiaryEntries: this.removeAllDiaryEntries,
    };

    window.mapCommonController.mapClickActionDelegate(this.mapClickAction);
    this.map().on('dblclick', this.clearPendingDiaryMapNewEntry);
  };

  disconnect() {
    this.clearPendingDiaryMapNewEntry();
    const map = window.mapCommonController?.map;
    if (map) {
      map.off('dblclick', this.clearPendingDiaryMapNewEntry);
    }
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

  clearPendingDiaryMapNewEntry = () => {
    if (this.pendingDiaryMapNewEntryTimeout !== null) {
      clearTimeout(this.pendingDiaryMapNewEntryTimeout);
      this.pendingDiaryMapNewEntryTimeout = null;
    }
  };

  mapClickAction = (e) => {
    this.clearPendingDiaryMapNewEntry();
    const { lat, lng } = e.latlng;
    this.pendingDiaryMapNewEntryTimeout = setTimeout(() => {
      this.pendingDiaryMapNewEntryTimeout = null;
      Turbo.visit(
        Routing.generate('diaryEntry_new', { lat, lon: lng, trip: tripId }),
        { frame: 'diaryEntry-new' },
      );
      sidebarController.switchToSidebarAction(true);
    }, DIARY_MAP_NEW_ENTRY_CLICK_MS);
  };

  // Marker related

  addDiaryEntry = (id, lat, lon, symbol, popup) => {
    this.diaryEntries[id] = createDraggableMarker(
      id,
      lat,
      lon,
      symbol,
      'diaryEntry_move',
      'sidebar-diaryEntries',
    )
      .bindPopup(popup)
      .addTo(this.map());
  };

  updateDiaryEntry = (id, symbol, popup) => {
    const marker = this.diaryEntries[id];
    if (!marker) {
      return;
    }
    marker.setIcon(iconSymbol(symbol));
    marker.getPopup().setContent(popup);
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
