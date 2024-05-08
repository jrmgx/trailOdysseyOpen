// noinspection JSUnusedGlobalSymbols

import L from 'leaflet';
import '@elfalem/leaflet-curve';
import { Controller } from '@hotwired/stimulus';
import { iconSymbol } from '../helpers';
import '../js/leaflet-double-touch-drag-zoom';

export default class extends Controller {
  static targets = [
    'map',
    'publicBar',
  ];

  static values = {
    options: Object,
    urls: Object,
    tiles: Array,
    translations: Object,
  };

  connect = () => {
    this.diaryEntries = new Map();
    this.cache = {};
    this.zoom = 10;

    // We add a wrapper to img
    for (const i of this.publicBarTarget.querySelectorAll('img[loading=lazy]')) {
      const w = document.createElement('span');
      w.classList.add('img-wrapper');
      i.after(w);
      w.append(i);
    }

    // TODO id there is a fragment go to that diary

    // Export method for external use
    window.publicController = {
      addDiaryEntry: this.addDiaryEntry,
    };
  };

  map = () => window.mapCommonController.map;

  // Actions

  markerClick = (id) => {
    this.showPublicBarClickAction();
    this.showOnPublicBar(id);
    this.showOnMap(id);
  };

  hidePublicBarClickAction = () => {
    this.publicBarTarget.classList.add('d-none');
    this.mapTarget.classList.add('map-fullscreen');
    this.map().invalidateSize();
  };

  showPublicBarClickAction = () => {
    this.publicBarTarget.classList.remove('d-none');
    this.mapTarget.classList.remove('map-fullscreen');
    this.map().invalidateSize();
  };

  currentDiaryClickAction = (e) => {
    const { id } = e.params;
    this.showOnMap(id);
  };

  prevDiaryClickAction = (e) => {
    const { id } = e.params;
    const ar = Array.from(this.diaryEntries.keys());
    const index = ar.findIndex((i) => i === `${id}`);
    if (index === 0) {
      // Show title screen
      this.showOnPublicBar(0);
      this.fitBounds();
      return;
    }

    const prevId = ar[index - 1];
    this.showOnPublicBar(prevId);
    this.showOnMap(prevId);
  };

  nextDiaryClickAction = (e) => {
    const { id } = e.params;
    const ar = Array.from(this.diaryEntries.keys());
    const index = ar.findIndex((i) => i === `${id}`);
    const nextId = ar[index + 1];
    this.showOnPublicBar(nextId);
    this.showOnMap(nextId);
  };

  // Action methods

  showOnMap = (id) => {
    const marker = this.diaryEntries.get(`${id}`);
    this.map().flyTo(marker.getLatLng(), this.zoom + 1);
  };

  showOnPublicBar = (id) => {
    const allDiaryEntries = document.querySelectorAll('.diaryEntryMain');
    for (const allDiaryEntry of allDiaryEntries) {
      allDiaryEntry.classList.add('d-none');
    }
    const diaryEntry = document.getElementById(`diary${id}`);
    diaryEntry.classList.remove('d-none');
  };

  // Marker related

  addDiaryEntry = (id, lat, lon, symbol) => {
    const marker = L.marker([parseFloat(lat), parseFloat(lon)], {
      icon: iconSymbol(symbol),
      draggable: false,
    });
    marker.on('click', () => this.markerClick(id));
    marker.addTo(this.map());

    this.diaryEntries.set(`${id}`, marker);

    this.fitBounds();
  };

  fitBounds = () => {
    const latLngs = [];
    for (const diaryEntry of this.diaryEntries) {
      latLngs.push(diaryEntry[1].getLatLng());
    }

    this.map().fitBounds(L.latLngBounds(latLngs));
    this.zoom = this.map().getZoom();
  };

  // Event based

  // Helpers

  preventWarnings = () => {
    // Targets
    this.publicBarTarget = null;
    this.mapTarget = null;
    // Values
    this.urlsValue = { };
    this.translationsValue = { };
  };
}
