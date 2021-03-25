from django.db import models

class carousel(models.Model):
    image = models.ImageField(upload_to='portfolio/pics')
    heading = models.CharField(max_length=100)
    description = models.CharField(max_length=200)

class gallery(models.Model):
    image = models.ImageField(upload_to='portfolio/gallery') 
    location = models.CharField(max_length=100)
   

class googlemap(models.Model):
    googleurl = models.URLField(max_length=2000)

class archProject(models.Model):
    image = models.ImageField(upload_to='portfolio/pics')
    description = models.CharField(max_length=200)

class techProject(models.Model):
    image = models.ImageField(upload_to='portfolio/pics')
    description = models.CharField(max_length=200)

class bizProject(models.Model):
    image = models.ImageField(upload_to='portfolio/pics')
    description = models.CharField(max_length=200)

class client(models.Model):
    image = models.ImageField(upload_to='portfolio/clients')
 
    
    