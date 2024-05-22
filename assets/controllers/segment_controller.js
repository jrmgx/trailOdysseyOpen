// noinspection JSUnusedGlobalSymbols

import L from 'leaflet';
import '@elfalem/leaflet-curve';
import 'leaflet-routing-machine';
import 'leaflet-lasso';
import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';
import { addLatLonToUrl, removeFromMap } from '../helpers';
import '../js/leaflet-double-touch-drag-zoom';
import markerCircleFakeIconUrl from '../images/marker-circle-fake.png';

export default class extends Controller {
  static targets = [
    'map',
    'segmentMultipleDeleteForm',
  ];

  static values = {
    options: Object,
    urls: Object,
    tiles: Array,
    translations: Object,
    mapboxKey: String,
  };

  connect = () => {
    this.segments = {};
    this.cache = {};

    this.editPoints = [];

    this.editModeFor = null;
    this.editPolylineLayerGroup = null;

    this.actionPointForSegmentActive = false;

    this.actionNewItineraryActive = 0;
    this.actionNewItineraryMarkers = [];

    this.markerCircleFakeIcon = L.icon({
      iconUrl: markerCircleFakeIconUrl,
      iconSize: [32, 32],
      iconAnchor: [16, 16],
      popupAnchor: [0, 0],
    });

    this.router = L.Routing.mapbox(this.mapboxKeyValue, {
      profile: 'mapbox/walking',
    });

    this.lasso = L.lasso(this.map(), {});
    this.map().on('lasso.finished', this.lassoFinishAction);

    // Export method for external use
    window.segmentController = {
      addSegment: this.addSegment,
      editSegment: this.editSegmentWithPointsAction,
      updateSegment: this.updateSegment,
      newSegment: this.newSegmentWithPointsAction,
      newSegmentCommonCancelAction: this.newSegmentCommonCancelAction,
    };

    window.mapCommonController.mapClickActionDelegate(this.mapClickAction);
  };

  map = () => window.mapCommonController.map;

  // Actions

  lassoClickAction = () => {
    this.lasso.enable();
  };

  lassoFinishAction = (e) => {
    this.lasso.disable();
    // TODO later we could delete multiple points from a segment too // this.editModeFor
    // eslint-disable-next-line no-alert
    if (!window.confirm(this.translationsValue.areYouSureSegmentDeleteMulti)) {
      return;
    }
    const segmentsIds = [];
    for (const layer of e.layers) {
      segmentsIds.push(layer.segmentId || 0);
    }
    const idsInput = this.segmentMultipleDeleteFormTarget.querySelector('#segment_multiple_delete_ids');
    idsInput.value = segmentsIds.join(',');
    this.segmentMultipleDeleteFormTarget.submit();
  };

  sidebarSegmentClickAction = (e) => {
    const { id } = e.params;
    sidebarController.switchToMapAction();
    const marker = this.segments[id];
    if (e.shiftKey) {
      this.map().fitBounds(marker.getBounds());
    } else {
      this.map().setView(marker.getBounds().getCenter());
    }
    marker.bindTooltip('Active').openTooltip();
    setTimeout(() => marker.closeTooltip(), 2000);
  };

  sidebarEditSegmentClickAction = (e) => {
    this.sidebarSegmentClickAction(e);
    this.hideEditButtons();
  };

  pointForSegmentStartStopAction = (e) => {
    if (this.actionPointForSegmentActive) {
      this.pointForSegmentStopAction(e);
      return;
    }

    this.actionPointForSegmentActive = true;
    document.body.style.cursor = 'crosshair';
    this.mapTarget.style.cursor = 'crosshair';
    e.target.innerHTML = `${this.translationsValue.clickMapToAdd}<br>${
      this.translationsValue.andHereToFinish}`;
    sidebarController.switchToMapAction();
  };

  pointForSegmentStopAction = (e) => {
    this.actionPointForSegmentActive = false;
    document.body.style.cursor = 'default';
    this.mapTarget.style.cursor = 'grab';
    e.target.innerHTML = this.translationsValue.addPoints;
  };

  itineraryStartStopAction = (e) => {
    const { on, off } = e.params || {};
    const thatButton = e.target.closest('.btn');
    const otherButtons = Array.from(thatButton.parentElement.children).filter(
      (btn) => btn !== thatButton,
    );
    if (this.actionNewItineraryActive > 0) {
      thatButton.innerHTML = on;
      otherButtons.forEach((btn) => btn.classList.remove('hide'));
      this.itineraryStopAction(e);
      return;
    }

    this.actionNewItineraryActive = 2;

    document.body.style.cursor = 'crosshair';
    this.mapTarget.style.cursor = 'crosshair';
    thatButton.innerHTML = off;
    otherButtons.forEach((btn) => btn.classList.add('hide'));
    sidebarController.switchToMapAction();
  };

  itineraryStopAction = (preserveRouting) => {
    this.actionNewItineraryActive = 0;

    if (this.routingControl && !preserveRouting) {
      this.routingControl = removeFromMap(this.routingControl, this.map());
    }

    for (const m of this.actionNewItineraryMarkers) {
      removeFromMap(m, this.map());
    }
    this.actionNewItineraryMarkers = [];

    document.body.style.cursor = 'default';
    this.mapTarget.style.cursor = 'grab';
  };

  mapClickAction = (e) => {
    if (this.actionPointForSegmentActive) {
      this.mapClickAddPointToSegmentAction(e);
      return;
    }

    if (this.actionNewItineraryActive > 0) {
      this.mapClickAddMarkerToItineraryAction(e);
    }
  };

  mapClickAddPointToSegmentAction = (e) => {
    // We need to find which side we need to add the point to
    const point = { lat: e.latlng.lat, lon: e.latlng.lng, el: null };
    const topPoint = this.editPoints[0];
    const bottomPoint = this.editPoints[this.editPoints.length - 1];

    if (
      this.map().distance([topPoint.lat, topPoint.lon], e.latlng)
      < this.map().distance([bottomPoint.lat, bottomPoint.lon], e.latlng)
    ) {
      // topMarker is closer: unshift
      this.editPoints.unshift(point);
    } else {
      this.editPoints.push(point);
    }

    this.updateSegmentPointsDraw();
  };

  mapClickAddMarkerToItineraryAction = (e) => {
    this.actionNewItineraryActive -= 1;
    this.actionNewItineraryMarkers.push(
      L.marker(e.latlng, { icon: this.markerCircleFakeIcon }).addTo(this.map()),
    );

    if (this.actionNewItineraryActive === 1) {
      Turbo.visit(this.urlsValue.segmentNewItinerary, { frame: 'segment-new' });
      return;
    }

    if (this.actionNewItineraryActive !== 0) {
      return;
    }

    this.routingControl = L.Routing.control({
      waypoints: [
        this.actionNewItineraryMarkers[0].getLatLng(),
        this.actionNewItineraryMarkers[1].getLatLng(),
      ],
      router: this.router,
      lineOptions: { styles: [{ color: 'red', weight: 5 }] },
      createMarker: (i, point, n) => {
        const marker = L.marker(point.latLng, {
          draggable: true,
          icon: this.markerCircleFakeIcon,
        });

        if (i === 0 || i === n - 1) {
          return marker;
        }

        const divElement = document.createElement('div');
        divElement.classList.add('segment-marker');

        const deleteElement = document.createElement('a');
        deleteElement.innerHTML = this.translationsValue.deleteThisPoint;
        deleteElement.addEventListener('click', () => {
          this.routingControl.spliceWaypoints(i, 1);
        });

        divElement.appendChild(deleteElement);
        marker.bindPopup(divElement);

        return marker;
      },

    }).on('routeselected', (routeselectedEvent) => { // or routesfound?
      const { route } = routeselectedEvent;
      this.editPoints = route.coordinates.map(
        (latLng) => ({ lat: latLng.lat, lon: latLng.lng, el: null }),
      );

      let name = this.translationsValue.newSegment;
      if (route.name) {
        const nameParts = route.name.split(',');
        if (nameParts.length > 1) {
          name = `${nameParts[0].trim()} to ${nameParts[nameParts.length - 1].trim()}`;
        }
      }
      this.updateSegmentPointsForm(name);
    }).addTo(this.map());

    this.itineraryStopAction(true);
  };

  newSegmentDrawAction = () => {
    this.hideEditButtons();
    const center = this.map().getCenter();
    Turbo.visit(
      addLatLonToUrl(center.lat, center.lng, this.urlsValue.segmentNew),
      { frame: 'segment-new' },
    );
  };

  /**
   * This one will cancel new segment draw action or itinerary action
   */
  newSegmentCommonCancelAction = () => {
    this.endSegmentPointsDraw();
    this.itineraryStopAction();
  };

  newSegmentWithPointsAction = (points) => {
    this.endSegmentPointsDraw();
    this.hideEditButtons();

    this.editModeFor = -1;
    this.editPoints = points;

    if (points.length) {
      this.updateSegmentPointsDraw();
    }
  };

  editSegmentWithPointsAction = (id, points) => {
    this.endSegmentPointsDraw();
    this.hideEditButtons();

    this.editModeFor = id;
    this.editPoints = points;

    removeFromMap(this.segments[id], this.map());
    delete this.segments[id];

    this.updateSegmentPointsDraw();
  };

  // Segments display

  addSegment = (id, points, color) => {
    const latLon = [];
    for (const p of points) {
      latLon.push([p.lat, p.lon]);
    }
    const polyline = L.polyline(latLon, {
      color: `#${color}`,
      weight: 5,
    });
    polyline.on('click', () => {
      const sidebar = document.querySelector('.sidebar');
      const element = document.getElementById(`segment_${id}`);
      if (!element || !sidebar) {
        return;
      }
      sidebar.scrollTo({ top: element.offsetTop });
      element.querySelector('.card').classList.add('text-bg-info');
      setTimeout(() => element.querySelector('.card').classList.remove('text-bg-info'), 2000);
      this.sidebarSegmentClickAction({ params: { id } });
    }).addTo(this.map());
    polyline.segmentId = id;
    if (this.segments[id]) {
      removeFromMap(this.segments[id], this.map());
    }
    this.segments[id] = polyline;
  };

  updateSegment = (id, points, color) => {
    this.endSegmentPointsDraw();
    this.addSegment(id, points, color);
  };

  /**
   * Segments edit display
   * Updating segment points draw WILL update the form too
   */
  updateSegmentPointsDraw = () => {
    // Remove deleted
    this.editPoints = this.editPoints.filter((point) => !point.deleted);

    if (this.editPolylineLayerGroup) {
      removeFromMap(this.editPolylineLayerGroup, this.map());
    }
    this.editPolylineLayerGroup = L.featureGroup();

    // Draw points
    const latLngs = [];
    for (const point of this.editPoints) {
      this.createEditSegmentMarker(point, this.map()).addTo(this.editPolylineLayerGroup);
      latLngs.push([point.lat, point.lon]);
    }

    // Draw halfway points
    for (let i in this.editPoints) {
      i = parseInt(i, 10);
      if (typeof this.editPoints[i + 1] === 'undefined') {
        break;
      }
      const lat = (parseFloat(this.editPoints[i].lat) + parseFloat(this.editPoints[i + 1].lat)) / 2;
      const lng = (parseFloat(this.editPoints[i].lon) + parseFloat(this.editPoints[i + 1].lon)) / 2;
      this.createEditHalfMarker(lat, lng, i).addTo(this.editPolylineLayerGroup);
    }

    // Draw polyline
    const polyline = L.polyline(latLngs, { color: '#0d6efd', weight: 5 });

    this.editPolylineLayerGroup.addLayer(polyline).addTo(this.map());
    this.editPolylineLayerGroup.bringToBack();

    this.updateSegmentPointsForm();
  };

  endSegmentPointsDraw = () => {
    this.showEditButtons();
    this.editModeFor = null;

    if (this.editPolylineLayerGroup) {
      removeFromMap(this.editPolylineLayerGroup, this.map());
    }
    this.editPolylineLayerGroup = null;
  };

  /**
   * Updating segment points form WILL NOT update the drawing
   */
  updateSegmentPointsForm = (name) => {
    // Remove deleted
    this.editPoints = this.editPoints.filter((point) => !point.deleted);

    // Update form
    const jsonPointsInputElement = document.getElementById('segment_jsonPoints');
    jsonPointsInputElement.value = JSON.stringify(this.editPoints);

    const nameInputElement = document.getElementById('segment_name');
    if (nameInputElement.value.length === 0) {
      nameInputElement.value = name || this.translationsValue.newSegment;
    }
  };

  // Event based

  mapZoomMoveEndHandler = () => {
    sidebarController.sendMapOptionDebounced(this.map().getZoom(), this.map().getCenter());
  };

  // Helpers

  hideEditButtons = () => {
    document.querySelectorAll('.js-segment-edit-action')
      .forEach((e) => e.classList.add('hide'));
  };

  showEditButtons = () => {
    document.querySelectorAll('.js-segment-edit-action')
      .forEach((e) => e.classList.remove('hide'));
  };

  // Inspired from https://stackoverflow.com/a/43417693/696517
  createEditSegmentMarker = (point, map) => {
    const marker = L.circleMarker([point.lat, point.lon], { color: '#0d6efd', radius: 10 });

    // Event listeners

    const eventListenerDragging = (evt) => {
      marker.dragActive = true;
      marker.closePopup();
      marker.setLatLng(evt.latlng);
      point.lat = evt.latlng.lat;
      point.lon = evt.latlng.lng;
    };

    const eventListenerOpenPopup = () => {
      if (!marker.dragActive) {
        marker.openPopup();
      }
      marker.dragActive = false;
    };

    const eventListenerDragStart = () => {
      map.dragging.disable();
      map.on('mousemove', eventListenerDragging);
    };

    const eventListenerDragStop = () => {
      map.dragging.enable();
      map.off('mousemove', eventListenerDragging);
      if (marker.dragActive) {
        setTimeout(this.updateSegmentPointsDraw, 1);
      }
    };

    const eventListenerSplit = (_, skipConfirm) => {
      // eslint-disable-next-line no-alert
      if (!skipConfirm && !window.confirm(this.translationsValue.areYouSureSegmentSplit)) {
        return;
      }
      marker.closePopup();
      const latLng = marker.getLatLng();
      const url = addLatLonToUrl(latLng.lat, latLng.lng, this.urlsValue.segmentSplit)
        .replace(/\/0$/, `/${this.editModeFor}`);

      Turbo.visit(url, { frame: 'sidebar-segments' });
    };

    const eventListenerDelete = (_, skipConfirm) => {
      // eslint-disable-next-line no-alert
      if (!skipConfirm && !window.confirm(this.translationsValue.areYouSureSegmentDelete)) {
        return;
      }
      point.deleted = true;
      marker.closePopup();
      this.updateSegmentPointsDraw();
    };

    const eventListenerCreateGhostMarker = () => {
      marker.setStyle({ color: '#FFFFFF00' }); // Hide
      const editGhostMarker = L.marker(marker.getLatLng(), {
        draggable: true,
        icon: this.markerCircleFakeIcon,
      });
      editGhostMarker.on('dragend', () => {
        point.lat = editGhostMarker.getLatLng().lat;
        point.lon = editGhostMarker.getLatLng().lng;
        removeFromMap(editGhostMarker, this.map);
        this.updateSegmentPointsDraw();
      });
      editGhostMarker.addTo(this.editPolylineLayerGroup);
    };

    // Core

    const divElement = document.createElement('div');
    divElement.classList.add('segment-marker');

    const splitElement = document.createElement('a');
    splitElement.innerHTML = this.translationsValue.splitAtThisPoint;
    splitElement.addEventListener('click', eventListenerSplit);

    const deleteElement = document.createElement('a');
    deleteElement.innerHTML = this.translationsValue.deleteThisPoint;
    deleteElement.addEventListener('click', eventListenerDelete);

    divElement.appendChild(splitElement);
    divElement.appendChild(deleteElement);
    marker.bindPopup(divElement);

    marker.dragActive = false;
    marker.associatedPoint = point;
    marker.off('click'); // Remove default that would show the popup

    if (L.Browser.mobile && L.Browser.touch) { // Mobile
      marker.on('click', eventListenerCreateGhostMarker);
      marker.on('contextmenu', eventListenerOpenPopup);
    } else {
      marker.on('mousedown', eventListenerDragStart);
      marker.on('mouseup', eventListenerDragStop);
      marker.on('click', eventListenerOpenPopup);
      marker.on('contextmenu', () => eventListenerDelete(null, true));
    }

    return marker;
  };

  createEditHalfMarker = (lat, lng, index) => {
    const marker = L.circleMarker([lat, lng], { color: '#888', radius: 8 });
    marker.on('click', () => {
      this.editPoints.splice(index + 1, 0, { lat, lon: lng, el: null });
      this.updateSegmentPointsDraw();
    });
    return marker;
  };

  preventWarnings = () => {
    // Targets
    this.mapTarget = null;
    this.segmentMultipleDeleteFormTarget = null;
    // Values
    this.optionsValue = { center: { lat: null, lon: null } };
    this.tilesValue = null;
    this.mapboxKeyValue = null;
    this.urlsValue = {
      segmentNew: null,
      segmentSplit: null,
      segmentNewItinerary: null,
    };
    this.translationsValue = {
      areYouSureSegmentDeleteMulti: null,
      areYouSureSegmentDelete: null,
      areYouSureSegmentSplit: null,
      addPoints: null,
      newSegment: null,
      duplicateThisPoint: null,
      splitAtThisPoint: null,
      deleteThisPoint: null,
      clickMapToAdd: null,
      clickMapToAddStartFinish: null,
      andHereToFinish: null,
      orHereToCancel: null,
    };
  };
}
