import L from 'leaflet';
import markerIconUrl from './images/marker.png';
import markerDefaultIconUrl from './images/leaflet/marker-icon.png';
import liveIconUrl from './images/gps_point_2x.png';

export const markerDefaultIcon = L.icon({
  iconUrl: markerDefaultIconUrl,
  iconSize: [25, 41],
  iconAnchor: [12, 20],
  popupAnchor: [0, 0],
});

export const addLatLonToUrl = (lat, lon, url) => url.replace('_LAT_', lat).replace('_LON_', lon);

export const iconSymbol = (symbol) => L.divIcon({
  html: `<span class="stage-marker"><img alt="" src="${markerIconUrl}${'">%</span>'.replace('%', symbol)}`,
  iconSize: [48, 48],
  iconAnchor: [24, 42],
  popupAnchor: [0, 16],
});

export const iconLive = L.icon({
  iconUrl: liveIconUrl,
  shadowUrl: '',
  iconSize: [32, 32], // size of the icon
  shadowSize: [0, 0], // size of the shadow
  iconAnchor: [16, 16], // point of the icon which will correspond to marker's location
  shadowAnchor: [0, 0], // the same for the shadow
  popupAnchor: [16, 16], // point from which the popup should open relative to the iconAnchor
});

export const curve = (startPoint, endPoint, pathOptions) => {
  // From: https://gist.github.com/ryancatalani/6091e50bf756088bf9bf5de2017b32e6
  const latlng1 = [startPoint.lat, startPoint.lng];
  const latlng2 = [endPoint.lat, endPoint.lng];
  const offsetX = latlng2[1] - latlng1[1];
  const offsetY = latlng2[0] - latlng1[0];
  const r = Math.sqrt(offsetX ** 2 + offsetY ** 2);
  const theta = Math.atan2(offsetY, offsetX);
  const thetaOffset = (3.14 / 10);
  const r2 = (r / 2) / (Math.cos(thetaOffset));
  const theta2 = theta + thetaOffset;
  const midpointX = (r2 * Math.cos(theta2)) + latlng1[1];
  const midpointY = (r2 * Math.sin(theta2)) + latlng1[0];
  const midpointLatLng = [midpointY, midpointX];
  // noinspection JSUnresolvedFunction
  return L.curve(['M', latlng1, 'Q', midpointLatLng, latlng2], pathOptions);
};

export const removeFromMap = (toRemove, map) => {
  if (!toRemove) {
    return null;
  }
  map.removeLayer(toRemove);
  toRemove.remove();
  return null;
};

// BUG add new stage to the map / add new diary on the map
// TODO on touch screen update live elevation/map on mouse use shift
