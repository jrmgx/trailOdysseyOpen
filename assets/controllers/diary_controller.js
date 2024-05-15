// noinspection JSUnusedGlobalSymbols

import L from 'leaflet';
import '@elfalem/leaflet-curve';
import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';
import {
  iconSymbol, addLatLonToUrl,
} from '../helpers';
import '../js/leaflet-double-touch-drag-zoom';

export default class extends Controller {
  static targets = [
    'myLivePosition',
  ];

  static values = {
    options: Object,
    urls: Object,
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
    this.map().locate({ setView: true, maxZoom: 16 });
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
      addLatLonToUrl(center.lat, center.lng, this.urlsValue.photoNew),
      { frame: 'diaryEntry-new' },
    );
  };

  mapClickAction = (e) => {
    Turbo.visit(
      addLatLonToUrl(e.latlng.lat, e.latlng.lng, this.urlsValue.diaryEntryNew),
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
          addLatLonToUrl(position.lat, position.lng, this.urlsValue.diaryEntryMove).replace('/0/', `/${id}/`),
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
      let diaryEntry = this.diaryEntries[index];
      diaryEntry.remove();
      diaryEntry = null;
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

    const marker = mapCommonController.addElement(e.latlng.lat, e.latlng.lng, divElement);

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
      addLatLonToUrl(latLng.lat, latLng.lng, this.urlsValue.diaryEntryNew),
      { frame: 'diaryEntry-new' },
    );

    sidebarController.switchToSidebarAction(true);
    mapCommonController.removeAllElements();
  };

  mapLocationFoundUpdateProgress = (marker) => {
    const latLng = marker.getLatLng();
    Turbo.visit(
      addLatLonToUrl(latLng.lat, latLng.lng, this.urlsValue.diaryUpdateProgress),
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
    this.urlsValue = {
      mapSearch: null,
      stageNew: null,
      diaryEntryNew: null,
      photoNew: null,
      stageMove: null,
      diaryEntryMove: null,
      mapOption: null,
      diaryUpdateProgress: null,
    };
    this.translationsValue = {
      clickMapToAdd: null,
      orHereToCancel: null,
      more: null,
      less: null,
    };
  };
}
