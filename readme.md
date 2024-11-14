# Search web with Valhalla map matching

## Docker bridge

### Create docker bridge

```
docker network create BRIDGE_NAME --driver bridge
ex: docker network create web_server --driver bridge
```

### Check the bridge

```
docker inspect BRIDGE_NAME
ex: docker inspect web_server
```

## Valhalla container

You can run the container either with a linked directory, in which you have downloaded  your maps or you can run the container with a one line command which downloads the latest map specified by url (slower).

### Linked directory

```
docker run -dit --name CONTAINER_NAME --network BRIDGE_NAME -p PORT:PORT -v PATH\custom_files:/custom_files ghcr.io/gis-ops/docker-valhalla/valhalla:latest
ex: docker run -dit --name valhalla --network web_server -p 8002:8002 -v C:\Users\richard.buri\search_web\doker\custom_files:/custom_files ghcr.io/gis-ops/docker-valhalla/valhalla:latest
```

### One line

```
docker run -dit --name CONTAINER_NAME --network BRIDGE_NAME -p PORT:PORT -e tile_urls=MAP_URL ghcr.io/gis-ops/docker-valhalla/valhalla:latest
ex: docker run -dit --name valhalla --network web_server -p 8002:8002 -e tile_urls=https://download.geofabrik.de/europe/slovakia-latest.osm.pbf ghcr.io/gis-ops/docker-valhalla/valhalla:latest
```

Note: If you change the port, you need to change the `port` variable in `map_match.py` in `map_match` function.

## Search_Web container

You need to be in the directory which contains "Dockerfile" file

### Build image

```
docker build -t IMAGE_NAME .
ex: docker build -t search .
```

### Run container

```
docker run -dit -v PATH\show:/var/www/html/ -v PATH\data:/home/data --network BRIDGE_NAME --name CONTAINER_NAME -p PORT:PORT IMAGE_NAME 
ex: docker run -dit -v C:\Users\richard.buri\search_web\DP1\show:/var/www/html/ -v C:\Users\richard.buri\search_web\DP1\data:/home/data --network web_server --name search_gps -p 8090:80 search 
```

### Exec container

You can either run the container from Docker Desktop or run it from CLI:

```
docker container exec -it CONTAINER_NAME /bin/bash
ex: docker container exec -it search_gps /bin/bash
```

After that, you need to start Apache2 server and MySQL. You also need to import some settings to MySQL:

```
service apache2 start
service mysql start
mysql -u root < /home/data/import/db.sql
```

### Adding a dataset

Csv file should have columns track,lat,lon. Run the following line:

```
python3 PATH_TO_csv_to_geohash.py PATH_TO_CSV DBNAME DATASET_NAME
ex: python3 /home/data/import/csv_to_geohash.py /home/data/import/geolife.csv geolife "Geolife Dataset"
```

## Map match:

First, you need to prepare files for the script.

### Directory structure

You should have one directory, which contains two directories "Walk" and "Drive".

In each directory put the csv files, which contain your path data. For example:

```
root
|-- Drive
|   |-- 33718.csv
|   |-- 31783.csv
|-- Walk
|   |-- 93293.csv
|   |-- 44352.csv
|   |-- 764555.csv
|   |-- 354852.csv
```

### CSV structure

Your csv file should be two columns, first longitude, second lattitude, for example:

```
lon,lat
17.07299,48.151611
17.073095,48.151878
17.073084,48.15184
17.073084,48.151733
17.073048,48.151672
17.073011,48.151714
```

### Running script in docker container

```
python3 PATH_TO_map_match.py PATH_TO_ROOT CONTAINER_NAME debug[optional]
ex: python3 /home/data/import/map_match.py /home/data/test/ valhalla debug
ex: python3 /home/data/import/map_match.py /home/data/test/ valhalla
```

Running the script with debug parameter will generate 2 geojsons for each csv file for easy visualization. First file is the original points, the second one is mapmatched points. When the script is done, you will have a `database.csv` file in your ROOT folder, which can be added to Search_Web
