// noinspection JSUnusedGlobalSymbols

import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';
import Routing from 'fos-router';

export default class extends Controller {
  static targets = [
    'sidebar',
    'switchToSidebar',
    'switchToMap',
    'toggleVisibility',
    'myLivePosition',
    'searchContainer',
    'mapOptionForm',
  ];

  static values = {
    translations: Object,
  };

  connect = () => {
    this.sendMapOptionDebounceId = null;

    // Export method for external use
    window.sidebarController = {
      switchToMapAction: this.switchToMapAction,
      switchToSidebarAction: this.switchToSidebarAction,
      sendMapOptionDebounced: this.sendMapOptionDebounced,
      searchContainerHide: this.searchContainerHide,
      showVisibilityAction: this.showVisibilityAction,
    };
  };

  // Actions

  switchToMapAction = () => {
    this.switchToMapTarget.classList.add('hide');
    this.switchToSidebarTarget.classList.remove('hide');

    this.sidebarTarget.classList.add('hide-mobile');

    this.toggleVisibilityTarget.classList.remove('hide-mobile');
    if (this.hasMyLivePositionTarget) {
      this.myLivePositionTarget.classList.remove('hide-mobile');
    }
    if (this.hasSearchContainerTarget) {
      this.searchContainerTarget.classList.remove('hide-mobile');
    }
    const control = document.querySelector('.leaflet-control-layers');
    if (control) {
      control.classList.remove('hide-mobile');
    }
    const mapButtonHelp = document.querySelector('.map-button-help');
    if (mapButtonHelp) {
      mapButtonHelp.remove();
    }
  };

  /**
   * @param withScrollDown can be a boolean or a Stimulus event
   */
  switchToSidebarAction = (withScrollDown) => {
    this.switchToMapTarget.classList.remove('hide');
    this.switchToSidebarTarget.classList.add('hide');

    this.sidebarTarget.classList.remove('hide-mobile');

    this.toggleVisibilityTarget.classList.add('hide-mobile');
    if (this.hasMyLivePositionTarget) {
      this.myLivePositionTarget.classList.add('hide-mobile');
    }
    if (this.hasSearchContainerTarget) {
      this.searchContainerTarget.classList.add('hide-mobile');
    }
    const control = document.querySelector('.leaflet-control-layers');
    if (control) {
      control.classList.add('hide-mobile');
    }

    if (withScrollDown === true || (withScrollDown.params && withScrollDown.params.scroll)) {
      // We wait a bit because we may have also asked to switch tab and it needs to be done first
      setTimeout(() => {
        const sidebarBottomEndElement = document.getElementById('sidebar-bottom-end');
        sidebarBottomEndElement.scrollIntoView({ behavior: 'instant' });
      }, 100);
    }
  };

  mapVisibilityElements = () => document.querySelectorAll(
    '.leaflet-popup-pane,'
        + '.leaflet-tooltip-pane,'
        + '.leaflet-marker-pane,'
        + '.leaflet-shadow-pane,'
        + '.leaflet-overlay-pane',
  );

  toggleVisibilityAction = () => {
    for (const el of this.mapVisibilityElements()) {
      el.classList.toggle('hide');
    }
  };

  showVisibilityAction = () => {
    for (const el of this.mapVisibilityElements()) {
      el.classList.remove('hide');
    }
  };

  hideVisibilityAction = () => {
    for (const el of this.mapVisibilityElements()) {
      el.classList.add('hide');
    }
  };

  searchContainerAction = (e) => {
    this.searchContainerIsVisible = true;
    const container = this.searchContainerTarget;
    container.querySelector('form.hide').classList.remove('hide');
    e.target.remove();
  };

  searchContainerHide = () => {
    if (!this.searchContainerIsVisible) {
      return;
    }
    this.searchContainerIsVisible = false;
    Turbo.visit(
      Routing.generate('geo_elements', { trip: tripId }),
      { frame: 'geo-elements' },
    );
  };

  // Event based

  sendMapOption = (zoom, latLng) => {
    const form = this.mapOptionFormTarget;
    form.querySelector('#trip_map_option_mapZoom').value = zoom;
    form.querySelector('#trip_map_option_mapCenter_lat').value = latLng.lat;
    form.querySelector('#trip_map_option_mapCenter_lon').value = latLng.lng;

    const formData = new FormData(this.mapOptionFormTarget);
    const encodedData = new URLSearchParams(formData).toString();

    // noinspection JSIgnoredPromiseFromCall
    fetch(Routing.generate('trip_edit_map_option', { trip: tripId }), {
      method: 'POST',
      body: encodedData,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    });
  };

  sendMapOptionDebounced = (zoom, latLng) => {
    if (!this.hasMapOptionFormTarget) {
      return;
    }
    clearTimeout(this.sendMapOptionDebounceId);
    this.sendMapOptionDebounceId = setTimeout(() => this.sendMapOption(zoom, latLng), 1200 * 3);
  };

  // Helpers

  preventWarnings = () => {
    // Targets
    this.sidebarTarget = null;
    this.switchToSidebarTarget = null;
    this.switchToMapTarget = null;
    this.toggleVisibilityTarget = null;
    this.searchContainerTarget = null;
    this.hasSearchContainerTarget = null;
    this.myLivePositionTarget = null;
    this.hasMyLivePositionTarget = null;
    this.mapOptionFormTarget = null;
    this.hasMapOptionFormTarget = null;
    this.offlineButtonTarget = null;
    // Values
    // ...
  };
}
