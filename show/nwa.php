<?php
$title = "Track comparison by Needleman–Wunsch algorithm (NWA)";

require_once "header.php";

?>

  <!-- Jumbotron -->
  <div class="jumbotron">
        <h1 class="hidden-sm hidden-xs">Needleman–Wunsch</h1>
        <h3 class="hidden-lg hidden-md">Needleman–Wunsch</h3>
        <p class="lead">
        Track comparison by Needleman–Wunsch algorithm (NWA)
        </p>
    </div>


    <div class="row" id="home">
        <div class="col-lg-12">
            <div id='map'></div>
        </div> 
    </div>
    <div class="row">
        <div class="col-lg-12">
            <h3>Needleman–Wunsch algorithm comparison results</h3>
            <div id="nwa-results">
                Fill in tracks data and hit Compare.  
            </div>
            <div id="nwa-ilustration" style="max-width: 100%; overflow:scroll; padding-top:20px; padding-bottom:20px;">

            </div>    
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <h3>Needleman–Wunsch algorithm inputs</h3>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-10">
            
            <form class="form-inline alg-form" >
                <div class="form-group">
                    <label class="sr-only" for="nwaMatch">Match</label>
                    <div class="input-group">
                    <div class="input-group-addon">Match</div>
                    <input type="text" class="form-control" id="nwaMatch" placeholder="1" size="1" value="1">
                    <div class="input-group-addon">points</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="sr-only" for="nwaMismatch">Mismatch</label>
                    <div class="input-group">
                    <div class="input-group-addon">Mismatch</div>
                    <input type="text" class="form-control" id="nwaMismatch" placeholder="-1" size="1" value="-1">
                    <div class="input-group-addon">points</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="sr-only" for="nwaGap">Gap</label>
                    <div class="input-group">
                    <div class="input-group-addon">Gap</div>
                    <input type="text" class="form-control" id="nwaGap" placeholder="0" size="1" value="0">
                    <div class="input-group-addon">points</div>
                    </div>
                </div>
                <br>
                <div class="form-group">
                    <label class="sr-only" for="nwaAlpha">&alpha;</label>
                    <div class="input-group">
                    <div class="input-group-addon">&alpha;</div>
                    <input type="text" class="form-control" id="nwaAlpha" placeholder="75" size="1" value="75">
                    <div class="input-group-addon">%</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="sr-only" for="nwaBeta">&beta;</label>
                    <div class="input-group">
                    <div class="input-group-addon">&beta;</div>
                    <input type="text" class="form-control" id="nwaBeta" placeholder="3" size="1" value="3">
                    <div class="input-group-addon">locations</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="sr-only" for="nwaDistance">Distance</label>
                    <div class="input-group">
                    <div class="input-group-addon">Distance</div>
                    <input type="text" class="form-control" id="nwaDistance" placeholder="100" size="2" value="100">
                    <div class="input-group-addon">m</div>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-lg-2">
            <button id="nwa-compare" type="submit" class="btn btn-lg btn-block btn-primary">Compare</button>
        </div> 
    </div>
    <div class="row">
        <div class="col-lg-6">
            <h4>Track A</h4>
            <textarea id="nwa-track-a" class="form-control" rows="10">[[116.5862274169922,40.054949943999496],[116.58279418945314,40.051665005850715],[116.57558441162111,40.04391192408113],[116.57094955444337,40.038917946926716],[116.56562805175783,40.03444934152963],[116.55103683471681,40.02827166956048],[116.52168273925783,40.01841252357908],[116.51327133178712,40.01486288226098],[116.50400161743164,40.007500074635985],[116.49335861206056,39.99698040031151],[116.48202896118164,39.986590631428534],[116.47464752197267,39.98040862671509],[116.46314620971681,39.97054256712116],[116.45215988159181,39.97725164260922],[116.43808364868165,39.9858014704838],[116.43138885498048,39.98777435575286],[116.40134811401369,39.98711673366051]]</textarea>
        </div> 
        <div class="col-lg-6">
            <h4>Track B</h4>
            <textarea id="nwa-track-b" class="form-control" rows="10">[[116.50400161743164,40.007500074635985],[116.49335861206056,39.99698040031151],[116.48202896118164,39.986590631428534],[116.47464752197267,39.98040862671509],[116.46314620971681,39.97054256712116],[116.45215988159181,39.97725164260922],[116.43808364868165,39.9858014704838],[116.43138885498048,39.98777435575286],[116.40134811401369,39.98711673366051],[116.36821746826173,39.98619605209568],[116.32890701293947,39.984749241710226],[116.29440307617189,39.98343393295324],[116.29302978515626,39.989221102071994],[116.29817962646486,39.98935262294526],[116.29817962646486,39.99474476071587],[116.29817962646486,39.996454374049726],[116.30118370056154,39.99625711315695],[116.3035011291504,39.99836119997057]]</textarea>
        </div> 
    </div>

  
    <script>
    var map = L.map('map').setView([48.151893,17.073541], 13);
    var gdata;
    var resultGroup = [];

    L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoibWFyb3NjIiwiYSI6ImNrb3B4b2QxeTBweG0ycWw0bTBiYWVwcWgifQ.g79td3RKqhZ9DEOLF9nGlA', {
        maxZoom: 18,
        attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, ' +
            'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>, <a href="https://www.microsoft.com/en-us/download/details.aspx?id=52367">Geolife Data set</a>',
        id: 'mapbox/streets-v11',
        tileSize: 512,
        zoomOffset: -1
    }).addTo(map);

    
    function showCirleA(item) {
        var colors = {"+": "#014cb5", "_": "#F56C19", "x": "#F36060" };
        resultGroup.push(L.circle([item[0],item[1]], {radius: item[2]/2, color: colors[item[3]]}));//.addTo(map);
    }

    function showCirleB(item) {
        var colors = {"+": "#014cb5", "_": "#19F5AF", "x": "#43E726" };
        resultGroup.push(L.circle([item[0],item[1]], {radius: item[2]/2, color: colors[item[3]]}));//.addTo(map);
    }

  
    function showResults(data){
		
        var res = "<p>Tracks were interpolated by "+data['config']['distance']+" meters to retrieve comparable sequence of points. Interpolated circles are shown on map.</p>";
        res += "<p><span style='background-color: #014cb5'>&nbsp;&nbsp;</span>Match of tracks, <span style='background-color: #F56C19'>&nbsp;&nbsp;</span> gap of track A, ";
        res += "<span style='background-color: #F36060'>&nbsp;&nbsp;</span>mismatch of track A, <span style='background-color: #19F5AF'>&nbsp;&nbsp;</span> gap track B, ";
        res += "<span style='background-color: #43E726'>&nbsp;&nbsp;</span>mismatch of track B</p>";
   
        res += "<p>Paramters used for comparison by NWA [match/mismatch/gap] = "+data['config']['match']+"/"+data['config']['mismatch']+"/"+data['config']['gap']+".</p>";
        res += "<p>To be similar is required minimum "+data['config']['alpha']+"% matches of total clusters from longer track and not more than "+data['config']['beta']+" subsequent mismatches in row.</p>";
        res += "<p>Number of matches "+data['matches']+" from total of "+data['alig1'].length+" with maximum of "+ data['max_subseq_mismatches']+" subsequent mismatches+gaps.</p>";
        res += "<p>Track are <strong>";
        if (data['similar']==false){
            res+="not";
        }
        res+=" similar</strong>. Similarity is <strong>"+data['similarity']+" %</strong> of longer track.<p>";

        res += "<p> Ilustration of alignment: + - match, x - mismatch,  _  - gap (in paused), o - gap (in running)</p>";
        $("#nwa-results").html(res);

        
        res = data['alig1'].join('')+"<br>"+data['alig2'].join('')
        
        $("#nwa-ilustration").html(res);
        
		
        if (resultGroup!=null){
            map.removeLayer(resultGroup);
            resultGroup = [];
        }
      
		if (data['seq1']) {
            data['seq1'].forEach(showCirleA);
		}

        if (data['seq2']) {
            data['seq2'].forEach(showCirleB);
		}
        
        resultGroup= L.featureGroup(resultGroup)
            .addTo(map);

        map.fitBounds(resultGroup.getBounds())
    }

   
	
	function compareNWA() {
        
        var params =  {
            "pattern": $("#nwa-track-a").val(), "search":$("#nwa-track-b").val(), 
            "nwa_match": $("#nwaMatch").val(), "nwa_mismatch" : $("#nwaMismatch").val(),
            "nwa_gap": $("#nwaGap").val(), "alpha": $("#nwaAlpha").val(), 
            "beta": $("#nwaBeta").val(), "distance": $("#nwaDistance").val() 
        };

        if (resultGroup!=null){
            map.removeLayer(resultGroup);
            resultGroup = [];
        }
        $("#nwa-results").html("");
        $("#nwa-ilustration").html("");
        $("#nwa-compare").attr("disabled", true);
        $("#nwa-compare").text("Comparing...");

        $.ajax({
            method: "POST",
            url: "nwa_py.php",
            dataType: "json",
            data: params
        })
            .done(function (json) {
                showResults(json);
                $("#nwa-compare").removeAttr("disabled");
                $("#nwa-compare").text("Compare");
            })
            .fail(function (jqxhr, textStatus, error) {
                var err = textStatus + ", " + error;
                $("#nwa-compare").removeAttr("disabled");
                $("#nwa-compare").text("Compare");
            });
    }

   
	
    $("#nwa-compare").click(compareNWA);
</script>

<?php require_once "footer.php"; ?>