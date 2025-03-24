import L from 'leaflet';
import * as Turbo from '@hotwired/turbo';
import Routing from 'fos-router';
import showToastWithUndo from './toast';
import { iconSymbol } from './helpers';

export default (id, lat, lon, symbol, moveUrl, frame) => {
  const originalPosition = { lat: parseFloat(lat), lon: parseFloat(lon) };

  return L.marker([originalPosition.lat, originalPosition.lon], {
    icon: iconSymbol(symbol),
    draggable: true,
  })
    .on('dragend', (event) => {
      const marker = event.target;
      const position = marker.getLatLng();

      showToastWithUndo(
        'Position updated',
        // Undo action
        () => {
          marker.setLatLng([originalPosition.lat, originalPosition.lon]);
          Turbo.visit(
            Routing.generate(moveUrl, {
              lat: originalPosition.lat,
              lon: originalPosition.lon,
              id,
              trip: tripId,
            }),
            { frame },
          );
        },
      );

      // Main action
      Turbo.visit(
        Routing.generate(moveUrl, {
          lat: position.lat,
          lon: position.lng,
          id,
          trip: tripId,
        }),
        { frame },
      );
    });
};
