from django.urls import path,include
from . import views

urlpatterns = [
    path('', views.blogs, name = 'blogpage'),
    path('blogs', views.blogs, name = 'blogpage')
]