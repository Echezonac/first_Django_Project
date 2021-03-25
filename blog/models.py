from django.db import models

# Create your models here.
class Blog(models.Model):
    image = models.ImageField(upload_to='portfolio/pics')
    title = models.CharField(max_length=100)
    description = models.TextField()
    date = models.DateField()
