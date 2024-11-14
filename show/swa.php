<?php
$title = "Track comparison by Smith–Waterman algorithm (SWA)";

require_once "header.php";

?>
<!-- Jumbotron -->
<div class="jumbotron">
      <h1 class="hidden-sm hidden-xs">Smith–Waterman</h1>
      <h3 class="hidden-lg hidden-md">Smith–Waterman</h3>
      <p class="lead">
      Track comparison by Smith–Waterman algorithm (SWA)
      </p>
  </div>

  <div class="row" id="home">
        <div class="col-lg-12">
            <div id='map-swa'></div>
        </div> 
    </div>
    <div class="row">
        <div class="col-lg-12">
            <h3>Smith–Waterman algorithm comparison results</h3>
            <div id="swa-results">
                Fill in tracks data and hit Compare.  
            </div>
            <div id="swa-ilustration" style="max-width: 100%; overflow:scroll; padding-top:20px; padding-bottom:20px;">

            </div>    
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <h3>Smith–Waterman algorithm inputs</h3>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-10">
            
            <form class="form-inline alg-form" >
                <div class="form-group">
                    <label class="sr-only" for="swaMatch">Match</label>
                    <div class="input-group">
                    <div class="input-group-addon">Match</div>
                    <input type="text" class="form-control" id="swaMatch" placeholder="1" size="1" value="1">
                    <div class="input-group-addon">points</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="sr-only" for="swaMismatch">Mismatch</label>
                    <div class="input-group">
                    <div class="input-group-addon">Mismatch</div>
                    <input type="text" class="form-control" id="swaMismatch" placeholder="-1" size="1" value="-1">
                    <div class="input-group-addon">points</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="sr-only" for="swaGap">Gap</label>
                    <div class="input-group">
                    <div class="input-group-addon">Gap</div>
                    <input type="text" class="form-control" id="swaGap" placeholder="-1" size="1" value="-1">
                    <div class="input-group-addon">points</div>
                    </div>
                </div>
                <br>
                
                <div class="form-group">
                    <label class="sr-only" for="swaMinScore">Min. score</label>
                    <div class="input-group">
                    <div class="input-group-addon">Min. score</div>
                    <input type="text" class="form-control" id="swaMinScore" placeholder="4" size="1" value="4">
                    <div class="input-group-addon">points</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="sr-only" for="swaDistance">Distance</label>
                    <div class="input-group">
                    <div class="input-group-addon">Distance</div>
                    <input type="text" class="form-control" id="swaDistance" placeholder="100" size="2" value="100">
                    <div class="input-group-addon">m</div>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-lg-2">
            <button id="swa-compare" type="submit" class="btn btn-lg btn-block btn-primary">Compare</button>
        </div> 
    </div>
    <div class="row">
        <div class="col-lg-6">
            <h4>Track A</h4>
            <textarea id="swa-track-a" class="form-control" rows="10">[[116.5862274169922,40.054949943999496],[116.58279418945314,40.051665005850715],[116.57558441162111,40.04391192408113],[116.57094955444337,40.038917946926716],[116.56562805175783,40.03444934152963],[116.55103683471681,40.02827166956048],[116.52168273925783,40.01841252357908],[116.51327133178712,40.01486288226098],[116.50400161743164,40.007500074635985],[116.49335861206056,39.99698040031151],[116.48202896118164,39.986590631428534],[116.47464752197267,39.98040862671509],[116.46314620971681,39.97054256712116],[116.45215988159181,39.97725164260922],[116.43808364868165,39.9858014704838],[116.43138885498048,39.98777435575286],[116.40134811401369,39.98711673366051]]</textarea>
        </div> 
        <div class="col-lg-6">
            <h4>Track B</h4>
            <textarea id="swa-track-b" class="form-control" rows="10">[[116.50400161743164,40.007500074635985],[116.49335861206056,39.99698040031151],[116.48202896118164,39.986590631428534],[116.47464752197267,39.98040862671509],[116.46314620971681,39.97054256712116],[116.45215988159181,39.97725164260922],[116.43808364868165,39.9858014704838],[116.43138885498048,39.98777435575286],[116.40134811401369,39.98711673366051],[116.36821746826173,39.98619605209568],[116.32890701293947,39.984749241710226],[116.29440307617189,39.98343393295324],[116.29302978515626,39.989221102071994],[116.29817962646486,39.98935262294526],[116.29817962646486,39.99474476071587],[116.29817962646486,39.996454374049726],[116.30118370056154,39.99625711315695],[116.3035011291504,39.99836119997057]]</textarea>
        </div> 
    </div>


    <script>
    var mapswa = L.map('map-swa').setView([48.151893,17.073541], 13);
    var gdata;
    var resultGroupSwa = [];
    

    L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoibWFyb3NjIiwiYSI6ImNrb3B4b2QxeTBweG0ycWw0bTBiYWVwcWgifQ.g79td3RKqhZ9DEOLF9nGlA', {
        maxZoom: 18,
        attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, ' +
            'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>, <a href="https://www.microsoft.com/en-us/download/details.aspx?id=52367">Geolife Data set</a>',
        id: 'mapbox/streets-v11',
        tileSize: 512,
        zoomOffset: -1
    }).addTo(mapswa);


    function showSwaCirleA(item) {
        var colors = {"+": "#014cb5", "_": "#F56C19", "x": "#F36060",".": "#F56C19" };
        resultGroupSwa.push(L.circle([item[0],item[1]], {radius: item[2]/2, color: colors[item[3]]}));//.addTo(map);
    }

    function showSwaCirleB(item) {
        var colors = {"+": "#014cb5", "_": "#19F5AF", "x": "#43E726", ".": "#19F5AF" };
        resultGroupSwa.push(L.circle([item[0],item[1]], {radius: item[2]/2, color: colors[item[3]]}));//.addTo(map);
    }


    function showResultsSwa(data){
		gdata = data;
        var res = "<p>Tracks were interpolated by "+data['config']['distance']+" meters to retrieve comparable sequence of points. Interpolated circles are shown on map.</p>";
        
        res += "<p><span style='background-color: #014cb5'>&nbsp;&nbsp;</span>Match of tracks, <span style='background-color: #F56C19'>&nbsp;&nbsp;</span> gap of track A, ";
        res += "<span style='background-color: #F36060'>&nbsp;&nbsp;</span>mismatch of track A, <span style='background-color: #19F5AF'>&nbsp;&nbsp;</span> gap track B, ";
        res += "<span style='background-color: #43E726'>&nbsp;&nbsp;</span>mismatch of track B</p>";
        res += "<p>Paramters used for comparison by SWA [match/mismatch/gap] = "+data['config']['match']+"/"+data['config']['mismatch']+"/"+data['config']['gap']+".</p>";
        res += "<p>To be similar is required minimum score "+data['config']['min_score']+". If SWA set to 1/-1/-1 it represents a number of subsequent matches.</p>";
        res += "<p>Tracks <strong>";
        if (data['similar']==false){
            res+="do not";
        }
        res+=" share similar </strong> part of maximum length "+data['matches']+" locations.<p>";

        res += "<p> Ilustration of alignment: + - match, x - mismatch,  _  - gap (in paused), o - gap (in running)</p>";
        $("#swa-results").html(res);

        
        res = data['alig1'].join('')+"<br>"+data['alig2'].join('')
        
        $("#swa-ilustration").html(res);
        
        if (resultGroupSwa!=null){
            mapswa.removeLayer(resultGroupSwa);
            resultGroupSwa = [];
        }
      
		if (data['seq1']) {
            data['seq1'].forEach(showSwaCirleA);
		}

        if (data['seq2']) {
            data['seq2'].forEach(showSwaCirleB);
		}
        
        resultGroupSwa= L.featureGroup(resultGroupSwa)
            .addTo(mapswa);

        mapswa.fitBounds(resultGroupSwa.getBounds())
    }


    function compareSWA() {
        
        var params =  {
            "pattern": $("#swa-track-a").val(), "search":$("#swa-track-b").val(), 
            "swa_match": $("#swaMatch").val(), "swa_mismatch" : $("#swaMismatch").val(),
            "swa_gap": $("#swaGap").val(), 
            "min_score": $("#swaMinScore").val(), "distance": $("#swaDistance").val() 
        };

        if (resultGroupSwa!=null){
            mapswa.removeLayer(resultGroupSwa);
            resultGroupSwa = [];
        }
        $("#swa-results").html("");
        $("#swa-ilustration").html("");
        $("#swa-compare").attr("disabled", true);
        $("#swa-compare").text("Comparing...");

        $.ajax({
            method: "POST",
            url: "swa_py.php",
            dataType: "json",
            data: params
        })
            .done(function (json) {
                showResultsSwa(json);
                $("#swa-compare").removeAttr("disabled");
                $("#swa-compare").text("Compare");
            })
            .fail(function (jqxhr, textStatus, error) {
                var err = textStatus + ", " + error;
                console.log(err);
                $("#swa-compare").removeAttr("disabled");
                $("#swa-compare").text("Compare");
            });
    }
	
    $("#swa-compare").click(compareSWA);
</script>

<?php require_once "footer.php"; ?>