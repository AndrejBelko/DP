import pandas as pd
import mysql.connector
import json
import sys

def runQuery(patterns, start_pos, end_pos, max_gap, min_match, dbName, type):
    mydb = mysql.connector.connect(
        host="localhost",
        user="search",
        password="password",
        database=dbName
    )

    mycursor = mydb.cursor()
    mycursor.execute("CREATE TEMPORARY TABLE hladac (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, kod varchar(7)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")
    mycursor.execute(
        "INSERT INTO hladac (kod) VALUES ('" + "'),('".join(patterns)+ "')"
    )
    mycursor.execute(
                f"select p.track as track, h.id as q_id from path p inner join hladac h on h.kod = p.hash where p.mapmatched = {type} order by p.track, p.id; "
            )

    myresult = pd.DataFrame(mycursor.fetchall(), columns=['id', 'pos'])
    found = []
    for name, dr in myresult.groupby('id'):
        # check if  was visiting stops in correct order as defined, this also identify, direction of line
        if dr['pos'].is_monotonic_increasing:
            found.append([
                name, len(dr['pos'].unique()),
                dr['pos'].min(), dr['pos'].max(), max(dr['pos'].diff().max()-1,0),
                dr['pos'].unique().tolist()
            ])


    r = pd.DataFrame(found, columns=['id', 'matches', 'start_pos', 'end_pos', 'max_gap',"path"]).sort_values(by=['matches', 'end_pos'],ascending=False)
    r = r.dropna()
    r = r[(r.matches>=min_match) & (r.start_pos<=start_pos) & (r.end_pos>=end_pos) & (r.max_gap<=max_gap) ]

    if len(r)==0:
        return pd.DataFrame([], columns=['id', 'matches', 'start_pos', 'end_pos', 'max_gap','route', 'path'])

    mycursor.execute(
        "select route as id, track from tracks where route in ("+str(r['id'].values.tolist())[1:-1]+");"
    )
    routes = pd.DataFrame(mycursor.fetchall(), columns=['id', 'route'])
    mycursor.close()
    mydb.disconnect()

    r = r.merge(routes, on='id')
    return r

if __name__ == '__main__':

    # print(sys.argv[1][1:-1].split(","), sys.argv[2], sys.argv[3], sys.argv[4], sys.argv[5], sys.argv[6])
    r = runQuery(sys.argv[1][1:-1].split(","), int(sys.argv[2]), int(sys.argv[3]), int(sys.argv[4]), int(sys.argv[5]), sys.argv[6], int(sys.argv[7]))

    print(json.dumps(r.values.tolist()))