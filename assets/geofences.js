'use strict';
document.querySelectorAll('[data-geofence-editor]').forEach(form => {
  const status=form.querySelector('[data-map-status]');
  if(!window.L){status.textContent='No se pudo cargar el mapa. Recarga la página.';return;}
  const field=form.elements.polygon;
  let points=JSON.parse(field.value),drawing=points.length===0;
  const map=L.map(form.querySelector('[data-fence-map]'),{doubleClickZoom:false}).setView([-33.4489,-70.6693],15);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{
    maxZoom:19,attribution:'© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap contributors</a>'
  }).on('tileerror',()=>{status.textContent='No se pudo cargar el fondo del mapa. Revisa tu conexión antes de dibujar.';}).addTo(map);
  const shape=L.polygon(points,{color:'#7655c9',fillOpacity:.25}).addTo(map);
  const handles=L.layerGroup().addTo(map);
  const update=()=>{
    field.value=JSON.stringify(points);shape.setLatLngs(points);
    status.textContent=`${points.length} vértices. ${drawing?'Pulsa el mapa para añadir puntos.':'Arrastra los puntos para ajustar el contorno.'}`;
    form.querySelector('[data-save-fence]').disabled=points.length<3;
    handles.clearLayers();
    points.forEach((p,i)=>{
      const marker=L.marker(p,{draggable:true,icon:L.divIcon({className:'fence-vertex',iconSize:[16,16],iconAnchor:[8,8]})}).addTo(handles);
      marker.on('dragend',()=>{const c=marker.getLatLng();points[i]=[Number(c.lat.toFixed(7)),Number(c.lng.toFixed(7))];update();});
    });
  };
  if(points.length) map.fitBounds(shape.getBounds(),{padding:[30,30],maxZoom:18});
  map.on('click',e=>{
    if(!drawing)return;
    if(points.length>=200){status.textContent='Máximo 200 vértices.';return;}
    points.push([Number(e.latlng.lat.toFixed(7)),Number(e.latlng.lng.toFixed(7))]);update();
  });
  form.querySelector('[data-draw]').onclick=()=>{drawing=true;update();};
  form.querySelector('[data-finish]').onclick=()=>{drawing=false;update();};
  form.querySelector('[data-undo]').onclick=()=>{points.pop();update();};
  form.querySelector('[data-clear]').onclick=()=>{points=[];drawing=true;update();};
  form.querySelector('[data-center-map]').onclick=()=>{
    const a=form.querySelector('[data-center-lat]'),b=form.querySelector('[data-center-lng]');
    if(!a.reportValidity() || !b.reportValidity() || a.value==='' || b.value==='')return;
    map.setView([Number(a.value),Number(b.value)],17);
  };
  form.addEventListener('submit',e=>{if(points.length<3){e.preventDefault();status.textContent='Dibuja al menos tres vértices.';}});
  update();
});
