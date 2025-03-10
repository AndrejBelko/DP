FROM ubuntu:20.04
RUN apt-get -y update
ARG DEBIAN_FRONTEND=noninteractive
ENV TZ=Europe/Bratislava
RUN apt-get install -y tzdata
RUN apt -y install apache2
RUN apt -y install php libapache2-mod-php php-mysql
RUN apt -y install mysql-server
RUN chmod -R 777 /run/mysqld
RUN apt-get -y install software-properties-common
RUN add-apt-repository -y ppa:deadsnakes/ppa
RUN apt-get -y update
RUN apt -y install python3.12
RUN apt -y install python3-pip
RUN pip3 install geopy
RUN pip3 install numpy
RUN pip3 install six
RUN pip3 install pandas
RUN pip3 install sqlalchemy
RUN pip3 install geojson
RUN pip3 install libgeohash
RUN pip3 install hausdorff
RUN pip3 install pymysql
RUN pip3 install numba
RUN pip3 install mysql-connector-python
RUN pip3 install python-csv
RUN pip3 install requests
RUN pip3 install gpxpy
RUN apt -y install npm
RUN pip3 install shapely
RUN pip3 install IPython
RUN pip3 install scipy
RUN npm install

# create db, mapmatched, uploads dirs in data/import/files

# BUILD IMAGE AND CREATE CONTAINER
# docker build -t search .
# docker run -dit -v C:\Users\maros\Documents\dockeer\mcomputing\search_web\show:/var/www/html/ -v C:\Users\maros\Documents\dockeer\mcomputing\search_web\data:/home/data --name search_gps -p 8090:80 search 

# FIRST TIME
#  docker container exec -it search_gps /bin/bash
#  service apache2 start
#  service mysql start
#  mysql -u root < /home/data/import/db.sql

# WHEN ADDING DATASET: place csv file with columns track,lat,lon 
#  python3 /home/data/import/csv_to_geohash.py path.to.csv dbName "name of dataset"
#
#  EXAMPLE:
#  python3 /home/data/import/csv_to_geohash.py /home/data/import/files/geolife.csv geolife "Geolife Dataset"

# After another start of container
#  docker container exec -it search_gps /bin/bash
#  service apache2 start
#  service mysql start