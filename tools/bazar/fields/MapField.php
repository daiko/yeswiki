<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"map", "carte_google"})
 */
class MapField extends BazarField
{
    protected $latitudeField;
    protected $longitudeField;
    protected $autocomplete;

    protected const FIELD_LATITUDE_FIELD = 1;
    protected const FIELD_LONGITUDE_FIELD = 2;
    protected const FIELD_AUTOCOMPLETE = 5;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->latitudeField = $values[self::FIELD_LATITUDE_FIELD] ?? 'bf_latitude';
        $this->longitudeField = $values[self::FIELD_LONGITUDE_FIELD] ?? 'bf_longitude';
        $this->autocomplete = $values[self::FIELD_AUTOCOMPLETE];

        $this->propertyName = 'carte_google';
    }

    protected function renderInput($entry)
    {
        $value = $this->getValue($entry);

        if ($this->autocomplete) {
            $autocompleteArray = explode(',', $this->autocomplete);
            $js = '$(document).ready(function () {
                $("input[name=\''.$autocompleteArray[0].'\'],input[name=\''.$autocompleteArray[1].'\']").attr("autocomplete", "off");
                var $inputcp = $("input[name=\''.$autocompleteArray[0].'\']");
                $inputcp.typeahead({
                  items: \'all\',
                  source: function(input, callback) {
                    var result = [];
                    if (input.length === 5) {
                      $.get("https://geo.api.gouv.fr/communes?codePostal="+input).done(function( data ) {
                        if (data.length > 0) {
                          $.each(data, function (index, value) {
                            result[index] = {id: value.codesPostaux[0], name: value.codesPostaux[0]+" "+value.nom, ville: value.nom}
                          });
                        } else {
                          result[0] = {id: input, name: \'Pas de ville trouvée pour le code postal \'+input};
                        }
                        callback(result);
                      });
                    } else {
                      result[0] = {id: input, name: \'Veuillez entrer 5 chiffres pour voir les villes associées au code postal\'};
                      callback(result);
                    }
                  },
                  autoSelect: false,
                  afterSelect: function(item) {
                    $inputcp.val(item.id);
                    $inputville.val(item.ville);
                    $(".btn-geolocate-address").click();
                  }
                });
                var $inputville = $("input[name=\''.$autocompleteArray[1].'\']");
                $inputville.typeahead({
                  items: 12,
                  minLength: 3,
                  source: function(input, callback) {
                    var result = [];
                    if (input.length >= 3) {
                      $.get("https://geo.api.gouv.fr/communes?nom="+input).done(function( data ) {
                        if (data.length > 0) {
                          $.each(data, function (index, value) {
                            result[index] = {id: value.codesPostaux[0], name: value.nom+" "+value.codesPostaux[0], ville: value.nom}
                          });
                        } else {
                          result[0] = {id: input, name: \'Pas de ville trouvée pour la recherche: \'+input};
                        }
                        callback(result);
                      });
                    } else {
                      result[0] = {id: input, name: \'Veuillez entrer les 3 premieres lettres pour voir les villes associées\'};
                      callback(result);
                    }
                  },
                  autoSelect: false,
                  afterSelect: function(item) {
                    $inputcp.val(item.id);
                    $inputville.val(item.ville);
                    $(".btn-geolocate-address").click();
                  }
                });
              });';
            $GLOBALS['wiki']->AddJavascript($js);
        }

        // on recupere d eventuels id et token pour les providers en ayant besoin
        $mapProvider = $GLOBALS['wiki']->config['baz_provider'];
        $mapProviderId = $GLOBALS['wiki']->config['baz_provider_id'];
        $mapProviderPass = $GLOBALS['wiki']->config['baz_provider_pass'];
        if (!empty($mapProviderId) && !empty($mapProviderPass)) {
            if ($mapProvider == 'MapBox') {
                $mapProviderCredentials = ', {id: \''.$mapProviderId .'\', accessToken: \''.$mapProviderPass.'\'}';
            } else {
                $mapProviderCredentials = ', { app_id: \''.$mapProviderId.'\', app_code: \''.$mapProviderPass.'\'}';
            }
        } else {
            $mapProviderCredentials = '';
        }

        $initMapScript = '
        $(document).ready(function() {
            // Init leaflet map
            var map = new L.Map(\'osmmapform\', {
                scrollWheelZoom:'.$GLOBALS['wiki']->config['baz_wheel_zoom'].',
                zoomControl:'.$GLOBALS['wiki']->config['baz_show_nav'].'
            });
            var geocodedmarker;
            var provider = L.tileLayer.provider("'.$mapProvider.'"'.$mapProviderCredentials.');
            map.addLayer(provider);
            
            map.setView(new L.LatLng('.$GLOBALS['wiki']->config['baz_map_center_lat'].', '.$GLOBALS['wiki']->config['baz_map_center_lon'].'), '.$GLOBALS['wiki']->config['baz_map_zoom'].');
            
            $("body").on("keyup keypress", "#bf_latitude, #bf_longitude", function(){
              var pattern = /^-?[\d]{1,3}[.][\d]+$/;
              var thisVal = $(this).val();
              if(!thisVal.match(pattern)) $(this).val($(this).val().replace(/[^\d.]/g,\'\'));
            });
            $("body").on("blur", "#bf_latitude, #bf_longitude", function() {
                var point = L.latLng($("#bf_latitude").val(), $("#bf_longitude").val());
                geocodedmarker.setLatLng(point);
                map.panTo(point, {animate:true}).zoomIn();
            });
            function showAddress(map) {
                var address = "";
                if (document.getElementById("bf_adresse")) address += document.getElementById("bf_adresse").value + \' \';
                if (document.getElementById("bf_adresse1")) address += document.getElementById("bf_adresse1").value + \' \';
                if (document.getElementById("bf_adresse2")) address += document.getElementById("bf_adresse2").value + \' \';
                if (document.getElementById("bf_ville")) address += document.getElementById("bf_ville").value + \' \';
                if (document.getElementById("bf_code_postal")) address += document.getElementById("bf_code_postal").value + \' \';
                address = address.replace(/\\("|\'|\\)/g, " ").trim();
                geocodage( address, showAddressOk, showAddressError );
                return false;
            }
            function showAddressOk( lon, lat )
            {
                //console.log("showAddressOk: "+lon+", "+lat);
                geocodedmarkerRefresh( L.latLng( lat, lon ) );
            }
        
            function showAddressError( msg )
            {
                //console.log("showAddressError: "+msg);
                if ( msg == "not found" ) {
                    alert("Adresse non trouvée, veuillez déplacer le point vous meme ou indiquer les coordonnées");
                    geocodedmarkerRefresh( map.getCenter() );
                } else {
                    alert("Une erreur est survenue: " + msg );
                }
            }
            function popupHtml( point ) {
                return "<div class=\"input-group\"><span class=\"input-group-addon\"><i class=\"fa fa-globe\"></i> Lat</span><input type=\"text\" class=\"form-control bf_latitude\" pattern=\"-?\\\d{1,3}\\\.\\\d+\" value=\""+point.lat+"\" /></div><br><div class=\"input-group\"><span class=\"input-group-addon\"><i class=\"fa fa-globe\"></i> Lon</span><input type=\"text\" pattern=\"-?\\\d{1,3}\\\.\\\d+\" class=\"form-control bf_longitude\" value=\""+point.lng+"\" /></div><br>Déplacer le point ailleurs si besoin ou modifier les coordonnées GPS.";
            }
        
            function geocodedmarkerRefresh( point )
            {
                if (geocodedmarker) map.removeLayer(geocodedmarker);
                geocodedmarker = L.marker(point, {draggable:true}).addTo(map);
                geocodedmarker.bindPopup(popupHtml( geocodedmarker.getLatLng() ), {closeButton: false, closeOnClick: false}).openPopup();
                map.panTo( geocodedmarker.getLatLng(), {animate:true});
                $(\'#bf_latitude\').val(point.lat);
                $(\'#bf_longitude\').val(point.lng);
        
                geocodedmarker.on("dragend",function(ev){
                    this.openPopup();
                    var changedPos = ev.target.getLatLng();
                    $(\'#bf_latitude\').val(changedPos.lat);
                    $(\'#bf_longitude\').val(changedPos.lng);
                    $(\'.bf_latitude\').val(changedPos.lat);
                    $(\'.bf_longitude\').val(changedPos.lng);
                });
            }
            $(\'.btn-geolocate-address\').on(\'click\', function(){showAddress(map);});
            $(\'body\').on(\'change\', \'.bf_latitude, .bf_longitude\', function(e) {
                if ($(this).is(":invalid")) {
                    $(\'#bf_latitude\').val(\'\');
                    $(\'#bf_longitude\').val(\'\');
                    alert(\'Format de coordonnées GPS non valide (que des chiffres et un point . pour les décimales)\');
                } else {
                    $(\'#bf_latitude\').val($(\'.bf_latitude\').val());
                    $(\'#bf_longitude\').val($(\'.bf_longitude\').val());
                    geocodedmarker.setLatLng([$(\'.bf_latitude\').val(), $(\'.bf_longitude\').val()]);
                    map.panTo( geocodedmarker.getLatLng(), {animate:true});
                }
            });';

        $GLOBALS['wiki']->AddJavascriptFile('tools/bazar/presentation/javascripts/geocoder.js');

        $geoCodingScript = '';
        $latitude = '';
        $longitude = '';
        if (isset($value)) {
            $tab = explode('|', $value);
            if (count($tab) > 1 && !empty($tab[0]) && !empty($tab[1])) {
                $latitude = $tab[0];
                $longitude = $tab[1];
                $geoCodingScript .= 'var point = L.latLng('.$latitude.', '.$longitude.');
                geocodedmarker = L.marker(point, {draggable:true}).addTo(map);
                map.panTo( geocodedmarker.getLatLng(), {animate:true});
                geocodedmarker.bindPopup(popupHtml( point ), {closeButton: false, closeOnClick: false});
                geocodedmarker.on("dragend",function(ev){
                    this.openPopup(point);
                    var changedPos = ev.target.getLatLng();
                    $(\'#bf_latitude\').val(changedPos.lat);
                    $(\'#bf_longitude\').val(changedPos.lng);
                    $(\'.bf_latitude\').val(changedPos.lat);
                    $(\'.bf_longitude\').val(changedPos.lng);
                });
                ';
            }
        }
        $geoCodingScript .= '});';

        $GLOBALS['wiki']->AddCSSFile('tools/bazar/libs/vendor/leaflet/leaflet.css');
        $GLOBALS['wiki']->AddJavascriptFile('tools/bazar/libs/vendor/leaflet/leaflet.js');
        $GLOBALS['wiki']->AddJavascriptFile('tools/bazar/libs/vendor/leaflet/leaflet-providers.js');
        $GLOBALS['wiki']->AddJavascript($initMapScript.$geoCodingScript);

        return $this->render("@bazar/inputs/map.twig", [
            'value' => $this->getValue($entry),
            'latitude' => $latitude,
            'longitude' => $longitude
        ]);
    }

    public function formatValuesBeforeSave($entry)
    {
        if (!$this->canEdit($entry)) {
            // retrieve value from value because redefined with right value
            $value = $this->getValue($entry);
            $values = (empty($value)) ? null : explode('|', $value);
            if (empty($values[0]) || empty($values[1])) {
                if (isset($entry[$this->getLatitudeField()])) {
                    unset($entry[$this->getLatitudeField()]);
                }
                if (isset($entry[$this->getLongitudeField()])) {
                    unset($entry[$this->getLongitudeField()]);
                }
            } else {
                $entry[$this->getLatitudeField()] = $values[0];
                $entry[$this->getLongitudeField()] = $values[1];
            }
        }
        if (!empty($entry[$this->getLatitudeField()]) && !empty($entry[$this->getLongitudeField()])) {
            $entry[$this->propertyName] = $entry[$this->getLatitudeField()] . '|' . $entry[$this->getLongitudeField()];
            return [
            $this->propertyName => $this->getValue($entry),
            $this->getLatitudeField() => $entry[$this->getLatitudeField()],
            $this->getLongitudeField() => $entry[$this->getLongitudeField()]
          ];
        } else {
            return [
          'fields-to-remove' => [
            $this->propertyName,
            $this->getLatitudeField(),
            $this->getLongitudeField()
            ]
        ];
        }
    }

    protected function renderStatic($entry)
    {
        return null;
    }

    // GETTERS. Needed to use them in the Twig syntax

    public function getLatitudeField()
    {
        return $this->latitudeField;
    }

    public function getLongitudeField()
    {
        return $this->longitudeField;
    }

    public function getAutocomplete()
    {
        return $this->autocomplete;
    }

    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
              'latitudeField' => $this->getLatitudeField(),
              'longitudeField' => $this->getLongitudeField(),
              'autocomplete' => $this->getAutocomplete(),
            ]
        );
    }
}
