import math, numpy as np
import json
from geopy.distance import great_circle
from geopy.point import Point
import sys

def swa_similarity(a, b, match, mismatch, distance):
    dist = int(great_circle((a[0], a[1]), (b[0], b[1])).meters)
    if dist < distance:
        return match
    return mismatch


def smithwaterman(sequence1, sequence2, match, mismatch, gap, distance):
    l1 = len(sequence1) + 1
    l2 = len(sequence2) + 1

    f = np.zeros((l1, l2), dtype=int)
    T = np.zeros((l1, l2), dtype=int)
    max_row = 0
    max_col = 0
    maxscore = 0

    for i in range(1, l1):
        for j in range(1, l2):
            mx = f[i - 1][j - 1] + swa_similarity(sequence1[i - 1], sequence2[j - 1], match, mismatch, distance)
            dx = f[i - 1][j] + gap
            ix = f[i][j - 1] + gap
            b = max(mx, dx, ix, 0)
            f[i][j] = b
            if b == 0:
                T[i][j] = 0
            else:
                T[i][j] = max3t(mx, dx, ix)

            if b > maxscore:
                max_row=i
                max_col=j
            maxscore = max(b, maxscore)
            sequence1[i - 1].append(".")
            sequence2[j - 1].append(".")


    a = []
    b = []
    
    i = max_row
    j = max_col
    while T[i][j] > 0:
        if T[i][j] == 1:
            if swa_similarity(sequence1[i - 1],sequence2[j - 1], match,mismatch, distance) == match:
                a.append("+")
                b.append("+")
                sequence1[i - 1][3]= "+"
                sequence2[j - 1][3]= "+"
            else:
                a.append("x")
                b.append("x")
                sequence1[i - 1][3]= "x"
                sequence2[j - 1][3]= "x"

            i -= 1
            j -= 1
        elif T[i][j] == 3:
            a.append("_")
            b.append("o")
            sequence2[j - 1][3]= "_"
            j -= 1
        elif T[i][j] == 2:
            a.append("o")
            b.append("_")
            sequence1[j - 1][3]= "_"
            i -= 1

    a.reverse()
    b.reverse()
    return maxscore, sequence1, sequence2, a, b


def max3t(v1, v2, v3):
    if v1 > v2:
        if v1 > v3:
            return 1
        else:
            return 3
    else:
        if v2 > v3:
            return 2
        else:
            return 3

def get_bearing(lat1, lon1, lat2, lon2):
    x = math.cos(math.radians(lat2)) * math.sin(math.radians(lon2 - lon1))
    y = math.cos(math.radians(lat1)) * math.sin(math.radians(lat2)) \
        - math.sin(math.radians(lat1)) * math.cos(math.radians(lat2)) \
        * math.cos(math.radians(lon2 - lon1))
    brng = np.rad2deg(math.atan2(x, y))
    if brng < 0:
        brng += 360
    return brng


def interpolate_path(locations, distance):
    lat_pos = 0
    lon_pos = 1

    i = 0
    result = []
    while i < len(locations):
        tmpla = locations[i][1] # match input data
        tmplo = locations[i][0] # match input data

        if tmpla < -90 or tmpla > 90 or tmplo < -180 or tmplo > 180:
            i += 1
            continue

        if i == 0:
            result.append([tmpla, tmplo,distance])
            i += 1
            continue

        p1 = result[-1]
        p2 = [tmpla, tmplo]
        d = int(great_circle((p1[lat_pos], p1[lon_pos]), (p2[lat_pos], p2[lon_pos])).meters)

        if d > distance:
            brng = get_bearing(p2[lat_pos], p2[lon_pos], p1[lat_pos], p1[lon_pos])
            xy = d - distance
            while xy >= 0:
                moved = great_circle().destination(Point(p2[lat_pos], p2[lon_pos]), brng, xy / 1000.0)
                result.append([moved.latitude, moved.longitude,distance])
                xy -= distance
        else:
            i += 1
    return result

def compareIt(params):
    pattern_in = json.loads(params[1])  # []
    search_in = json.loads(params[2])  # []
    swa_match = int(params[3])  # 1
    swa_mismatch = int(params[4])  # -1
    swa_gap = int(params[5])  # -1
    min_score = int(params[6])  # 4
    distance = int(params[7])  # 100

    pattern = interpolate_path(pattern_in, distance)
    search = interpolate_path(search_in, distance)
    max_score, align1, align2, ra, rb = smithwaterman(pattern, search, swa_match, swa_mismatch, swa_gap, distance)
    similar = False
    if max_score >= min_score : similar = True

    return {
        "matches": int(max_score),
        "similar": similar,
        "seq1": align1,
        "seq2": align2,
        "alig1": ra,
        "alig2": rb,
        "config": {"match": swa_match, "mismatch": swa_mismatch, "gap": swa_gap, "min_score": min_score, "distance": distance}
    }

def getARequest():
 return "[[116.5862274169922,40.054949943999496],[116.58279418945314,40.051665005850715],[116.57558441162111,40.04391192408113],[116.57094955444337,40.038917946926716],[116.56562805175783,40.03444934152963],[116.55103683471681,40.02827166956048],[116.52168273925783,40.01841252357908],[116.51327133178712,40.01486288226098],[116.50400161743164,40.007500074635985],[116.49335861206056,39.99698040031151],[116.48202896118164,39.986590631428534],[116.47464752197267,39.98040862671509],[116.46314620971681,39.97054256712116],[116.45215988159181,39.97725164260922],[116.43808364868165,39.9858014704838],[116.43138885498048,39.98777435575286],[116.40134811401369,39.98711673366051]]"

def getBRequest():
 return "[[116.50400161743164,40.007500074635985],[116.49335861206056,39.99698040031151],[116.48202896118164,39.986590631428534],[116.47464752197267,39.98040862671509],[116.46314620971681,39.97054256712116],[116.45215988159181,39.97725164260922],[116.43808364868165,39.9858014704838],[116.43138885498048,39.98777435575286],[116.40134811401369,39.98711673366051],[116.36821746826173,39.98619605209568],[116.32890701293947,39.984749241710226],[116.29440307617189,39.98343393295324],[116.29302978515626,39.989221102071994],[116.29817962646486,39.98935262294526],[116.29817962646486,39.99474476071587],[116.29817962646486,39.996454374049726],[116.30118370056154,39.99625711315695],[116.3035011291504,39.99836119997057]]"


if __name__ == '__main__':
    if len(sys.argv)>7:
        r = compareIt(sys.argv)
        print(json.dumps(r))
    else:
        print("{}")