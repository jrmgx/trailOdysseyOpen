// noinspection JSUnusedGlobalSymbols

import L from 'leaflet';
import '@elfalem/leaflet-curve';
import SmoothMarkerBouncing from 'leaflet.smooth_marker_bouncing';
import { Controller } from '@hotwired/stimulus';
import { iconSymbol } from '../helpers';
import '../js/leaflet-double-touch-drag-zoom';

SmoothMarkerBouncing(L);

export default class extends Controller {
  static targets = [
    'map',
    'publicBar',
  ];

  static values = {
    options: Object,
    tiles: Array,
    translations: Object,
  };

  connect = () => {
    this.diaryEntries = new Map();
    this.diaryCurrentIndex = null;
    this.cache = {};
    this.zoom = 10;
    this.fitPolyline = null;

    // We add a wrapper to img
    for (const i of this.publicBarTarget.querySelectorAll('img[loading=lazy]')) {
      const w = document.createElement('span');
      w.classList.add('img-wrapper');
      i.after(w);
      w.append(i);
    }

    window.addEventListener(
      'keydown',
      (event) => {
        if (event.defaultPrevented) {
          return; // Do nothing if the event was already processed
        }

        const ar = Array.from(this.diaryEntries.keys());
        const change = () => {
          const id = ar[this.diaryCurrentIndex];
          if (id) {
            this.showOnPublicBar(id);
            this.showOnMap(id);
            this.updateDiaryInUrl(id);
          }
        };

        switch (event.key) {
          case 'ArrowLeft':
          case 'p':
            if (this.diaryCurrentIndex !== null) {
              this.diaryCurrentIndex -= 1;
              if (this.diaryCurrentIndex < 0) {
                this.showTitleScreen();
                break;
              }
            }
            change();
            break;
          case 'ArrowRight':
          case 'n':
            if (this.diaryCurrentIndex === null) {
              this.diaryCurrentIndex = 0;
            } else {
              this.diaryCurrentIndex += 1;
              if (this.diaryCurrentIndex > ar.length - 1) {
                this.diaryCurrentIndex = ar.length - 1;
              }
            }
            change();
            break;
          default: break;
        }

        // Cancel the default action to avoid it being handled twice
        event.preventDefault();
      },
      true,
    );

    // Export method for external use
    window.publicController = {
      addDiaryEntry: this.addDiaryEntry,
      showDiaryFromUrl: this.showDiaryFromUrl,
      fit: this.fit,
    };
  };

  map = () => window.mapCommonController.map;

  // Init

  showDiaryFromUrl = () => {
    const url = new URL(document.location);
    if (url.hash) {
      const hash = parseInt(url.hash.substring(1), 10);
      if (hash) {
        this.showOnMap(hash);
        this.showOnPublicBar(hash);
      }
    }
  };

  updateDiaryInUrl = (id) => {
    const url = new URL(document.location);
    url.hash = `${id}`;
    document.location = url.toString();
  };

  // Actions

  markerClick = (id) => {
    this.showPublicBarClickAction();
    this.showOnPublicBar(id);
    this.showOnMap(id);
    this.updateDiaryInUrl(id);
  };

  hidePublicBarClickAction = () => {
    this.publicBarTarget.classList.add('d-none');
    this.mapTarget.classList.add('map-fullscreen');
    this.map().invalidateSize();
  };

  homePublicBarClickAction = () => {
    this.showTitleScreen();
  };

  showPublicBarClickAction = () => {
    this.publicBarTarget.classList.remove('d-none');
    this.mapTarget.classList.remove('map-fullscreen');
    this.map().invalidateSize();
  };

  currentDiaryClickAction = (e) => {
    const { id } = e.params;
    this.showOnMap(id);
    this.updateDiaryInUrl(id);
  };

  showTitleScreen = () => {
    this.diaryCurrentIndex = null;
    this.showOnPublicBar(0);
    this.updateDiaryInUrl('#');
    this.fit();
  };

  prevDiaryClickAction = (e) => {
    const { id } = e.params;
    const ar = Array.from(this.diaryEntries.keys());
    const index = ar.findIndex((i) => i === `${id}`);
    if (index === 0) {
      this.showTitleScreen();
      return;
    }

    const prevId = ar[index - 1];
    this.diaryCurrentIndex = index - 1;
    this.showOnPublicBar(prevId);
    this.showOnMap(prevId);
    this.updateDiaryInUrl(prevId);
  };

  nextDiaryClickAction = (e) => {
    const { id } = e.params;
    const ar = Array.from(this.diaryEntries.keys());
    const index = ar.findIndex((i) => i === `${id}`);
    const nextId = ar[index + 1];
    this.diaryCurrentIndex = index + 1;
    this.showOnPublicBar(nextId);
    this.showOnMap(nextId);
    this.updateDiaryInUrl(nextId);
  };

  // Action methods

  showOnMap = (id) => {
    const marker = this.diaryEntries.get(`${id}`) ?? false;
    if (!marker) return;
    this.map().flyTo(marker.getLatLng(), this.zoom + 1);
    marker.bounce();
  };

  showOnPublicBar = (id) => {
    const diaryEntry = document.getElementById(`diary${id}`);
    if (!diaryEntry) {
      return;
    }
    const allDiaryEntries = document.querySelectorAll('.diaryEntryMain');
    for (const allDiaryEntry of allDiaryEntries) {
      allDiaryEntry.classList.add('d-none');
    }
    diaryEntry.classList.remove('d-none');
    if (id === 0) return;
    const outerContainer = diaryEntry.querySelector('.public-bar-description');
    const innerContainer = outerContainer.querySelector('.markdown-container');
    if (!innerContainer.querySelector('img')) {
      const bodyWidth = document.querySelector('body').clientWidth;
      const max = Math.min(8, Math.round((0.0055 * bodyWidth) + 0.92));
      if (this.updateFontSizeToFit(outerContainer, innerContainer, max)) {
        outerContainer.classList.add('auto-sized');
      }
    }
  };

  // Marker related

  addDiaryEntry = (id, lat, lon, symbol) => {
    const marker = L.marker([parseFloat(lat), parseFloat(lon)], {
      icon: iconSymbol(symbol),
      draggable: false,
    });
    // https://github.com/hosuaby/Leaflet.SmoothMarkerBouncing
    marker.setBouncingOptions({
      contractHeight: 0,
      shadowAngle: null,
      elastic: false,
      exclusive: true,
    });
    marker.on('click', () => this.markerClick(id));
    marker.addTo(this.map());

    this.diaryEntries.set(`${id}`, marker);
  };

  fit = (arrayOfPoints = null) => {
    if (this.fitPolyline) {
      this.map().fitBounds(this.fitPolyline.getBounds());
      return;
    }

    if (!arrayOfPoints || arrayOfPoints.length < 2) return;
    this.fitPolyline = L.polyline(arrayOfPoints);
    this.map().fitBounds(this.fitPolyline.getBounds());
    this.zoom = this.map().getZoom();
  };

  // Event based

  // Helpers

  updateFontSizeToFit = (outerContainer, innerContainer, max) => {
    const outerHeight = outerContainer.clientHeight;
    let innerHeight = innerContainer.clientHeight;
    if (innerHeight >= outerHeight) {
      return false;
    }

    let fontSize = 1;
    while (innerHeight < outerHeight && fontSize <= max) {
      fontSize += 0.5;
      innerContainer.style.fontSize = `${fontSize}rem`;
      innerHeight = innerContainer.clientHeight;
    }
    // Keep previous iteration
    innerContainer.style.fontSize = `${Math.min(max, fontSize - 0.5)}rem`;
    return true;
  };

  preventWarnings = () => {
    // Targets
    this.publicBarTarget = null;
    this.mapTarget = null;
    // Values
    this.translationsValue = { };
  };
}
