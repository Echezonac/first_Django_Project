from django.shortcuts import render
from .models import carousel
from .models import gallery
from .models import googlemap
from .models import archProject
from .models import techProject
from .models import bizProject
from .models import client
from django.http import HttpResponse

# Create your views here.
def home(request):
    carousels=carousel.objects.all()
    pictures=gallery.objects.all()
    clients=client.objects.all()
    return render(request,'portfolio/index.html',{'carousels':carousels,'pictures':pictures,'clients':clients})

def projects(request):
     archworks=archProject.objects.all()
     techworks=techProject.objects.all()
     bizworks=bizProject.objects.all()
     return render(request,'portfolio/projects.html',{'archworks':archworks,'techworks':techworks,'bizworks':bizworks})

def solutions(request):
    return render(request,'portfolio/solutions.html')

def contact(request):
    locationMap =googlemap.objects.all()
    return render(request,'portfolio/contact.html', {'locationMap':locationMap})


