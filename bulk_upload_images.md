## Bulk upload images PathDB

### WSI file location

```
docker inspect -f '{{ .Mounts }}' "quip-pathdb"
```

Using this information, find out where the images directory is bound. 

Then, create subfolder on host system under that directory, and move the WSIs there.

### Manifest File
Create a manifest with file path relative to the container file system: `/data/pathdb/files/wsi/brca`, for example.

Your manifest file will be in csv format.  It will have 4 columns:

| path   |      studyid      |  clinicaltrialsubjectid |  imageid |
|----------|:-------------:|------:|------:|

Populate the columns accordingly.

### Bulk image upload
Sign in and click the <a href="">Bulk Upload Images</a> link in the left navigation panel.

Give it a Title.

Identify the target collection (note that the collection needs to be created beforehand. Use the <a href="">Add Collection</a> link in the left navigation panel.)

Select the manifest file that you've just created.

Hit Save.

Refresh the screen to view the upload status.