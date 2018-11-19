QuIP - PathDB

Building:
docker build -t quip_pathdb:2.5 pathdb

Running:
docker run --name quip_pathdb --net=quip_nw --restart unless-stopped -itd -p 80:80 quip_pathdb:2.5

